<?php

declare(strict_types=1);

namespace App\Services\Browser;

use App\Enums\BrowserTaskStatus;
use App\Models\BrowserTask;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

/**
 * 混合部署的健康狀態查詢。
 *
 * 背景：瀏覽器任務不在生產機上跑。生產 VPS 是 1 CPU / 2GB RAM，實測 Chromium
 * 載入 shopee.tw 峰值 RSS 2137MB（about:blank 地板值就 865MB），而那台機器已經
 * 跑 MySQL + Redis + PHP-FPM + Horizon —— 物理上塞不下瀏覽器。
 * 所以 browser queue 的消費者是 Paul 的 Mac（還附帶台灣住宅 IP 這個雲端買不到的
 * 反風控優勢），兩台機器透過 Tailscale 共用同一份 Redis 與 MySQL。
 *
 * 問題是 Mac 會關機、會睡眠、會換網路，而生產機完全看不到它。沒有心跳的話
 * 「任務積在佇列」與「Mac 離線」在 UI 上長得一模一樣，operator 只會看到商品
 * 卡在匯入中然後以為系統壞了。這個 class 就是把兩者分開的依據。
 *
 * ⚠️ 所有方法都是「查詢」，不做任何修復動作。修復是 browser:reap-stale 的事。
 */
final class BrowserHealth
{
    /** Mac worker 的心跳時間（ISO8601 字串，刻意不存 Carbon 物件以免 cache 驅動差異） */
    public const HEARTBEAT_KEY = 'browser:worker:heartbeat';

    /** 心跳來源的機器名稱（分辨到底是哪台在消費） */
    public const HEARTBEAT_HOST_KEY = 'browser:worker:host';

    /** 心跳 TTL：比判定門檻長很多，留著讓 UI 能顯示「最後心跳是幾分鐘前」 */
    public const HEARTBEAT_TTL_SECONDS = 300;

    /**
     * 存活判定門檻。
     *
     * 心跳每分鐘寫一次，門檻取 2 分鐘 = 容許漏掉一次（Mac 在跑 Chromium 時
     * 1 CPU 被吃滿、排程晚個幾十秒是常態）。再放寬就變成 Mac 睡著了還顯示綠燈。
     */
    public const ALIVE_SECONDS = 120;

    public const QUEUE = 'browser';

    /** 積壓多久算異常（分鐘）。widget 用這個值決定要不要變紅 */
    public const BACKLOG_WARNING_MINUTES = 30;

    public function workerAlive(): bool
    {
        // 刻意不用 diffInSeconds()：Carbon 2 的 diff 預設取絕對值、Carbon 3 改成帶正負號，
        // 升版時這種判斷會無聲反轉。用時間戳相減對兩個大版本都是同一個語意。
        return ($this->ageSeconds() ?? PHP_INT_MAX) <= self::ALIVE_SECONDS;
    }

    public function lastHeartbeat(): ?CarbonImmutable
    {
        $raw = Cache::get(self::HEARTBEAT_KEY);

        if (blank($raw)) {
            return null;
        }

        // 容忍舊格式（曾經存過 Carbon 物件）與正常的 ISO8601 字串
        return $raw instanceof \DateTimeInterface
            ? CarbonImmutable::instance($raw)
            : CarbonImmutable::parse((string) $raw);
    }

    public function workerHost(): ?string
    {
        $host = Cache::get(self::HEARTBEAT_HOST_KEY);

        return blank($host) ? null : (string) $host;
    }

    /** 心跳距今幾分鐘（無心跳回 null） */
    public function heartbeatAgeMinutes(): ?int
    {
        $seconds = $this->ageSeconds();

        return $seconds === null ? null : intdiv($seconds, 60);
    }

    /** 心跳距今幾秒（無心跳回 null；未來時間一律視為 0） */
    private function ageSeconds(): ?int
    {
        $last = $this->lastHeartbeat();

        return $last === null ? null : max(0, CarbonImmutable::now()->getTimestamp() - $last->getTimestamp());
    }

    /**
     * browser queue 的待處理數。
     *
     * ⚠️ 用 Queue::size() 而不是數 browser_tasks：BrowserTask 是「稽核紀錄」，
     *    由 worker 取到 job 之後才建立，佇列裡還沒被取走的 job 在 DB 裡不存在。
     *    要回答「還有沒有人在消費」只能問 Redis。
     */
    public function queueBacklog(): int
    {
        try {
            return (int) Queue::size(self::QUEUE);
        } catch (\Throwable) {
            // Redis 連不上時 widget 不該整個炸掉 —— 那正是最需要看到畫面的時候
            return 0;
        }
    }

    /** 最舊的 pending 任務已經等了幾分鐘（沒有 pending 回 null） */
    public function oldestPendingMinutes(): ?int
    {
        $oldest = BrowserTask::query()
            ->where('status', BrowserTaskStatus::Pending)
            ->min('queued_at');

        if (blank($oldest)) {
            return null;
        }

        $seconds = CarbonImmutable::now()->getTimestamp() - CarbonImmutable::parse((string) $oldest)->getTimestamp();

        return intdiv(max(0, $seconds), 60);
    }

    /**
     * 各動作本小時剩餘次數。
     *
     * 只有「每小時」一種視窗 —— BrowserRateLimiter 用固定視窗，沒有日上限。
     * 這裡順便把 max 一起回傳，UI 才有「3 / 20」這種人看得懂的呈現。
     *
     * @return array<string, array{label: string, remaining: int, max: int}>
     */
    public function rateLimitRemaining(): array
    {
        $limiter = app(BrowserRateLimiter::class);

        $labels = [
            'shopee_scrape' => '蝦皮抓取',
            'external_image' => '外站圖片',
            'session_check' => '登入檢查',
        ];

        $out = [];

        foreach ($labels as $action => $label) {
            $max = (int) config("services.browser.rate_limits.{$action}_per_hour", 20);
            $out[$action] = [
                'label' => $label,
                'remaining' => $limiter->remaining($action),
                'max' => $max,
            ];
        }

        return $out;
    }

    /** running 但超過門檻沒結束的任務數（Mac 睡眠／crash 的典型症狀） */
    public function staleTasks(int $minutes = 30): int
    {
        return BrowserTask::query()->stale($minutes)->count();
    }

    /** 需要人工介入的任務數（人機驗證、登入失效等） */
    public function needsManualTasks(): int
    {
        return BrowserTask::query()->where('status', BrowserTaskStatus::NeedsManual)->count();
    }

    /** 現在是不是允許抓取的時段（反爬要求，預設台灣時間 09:00–23:00） */
    public function withinWindow(): bool
    {
        return app(BrowserRateLimiter::class)->withinWindow();
    }
}
