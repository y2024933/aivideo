<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\BrowserTaskStatus;
use App\Filament\Resources\BrowserTaskResource;
use App\Services\Browser\BrowserHealth;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * 混合部署的狀態儀表。
 *
 * 這個 widget 存在的唯一目的：讓 operator 在生產機的 /admin 上分辨
 *   「瀏覽器任務還沒跑」到底是 (a) 正常排隊 (b) Mac 沒開機 (c) 真的壞了。
 * 沒有它的話三種情況在 UI 上完全一樣，而 operator 唯一能做的只有重按重試按鈕
 * —— 那會把 rate limit 用光，反而增加帳號風險。
 */
final class BrowserHealthWidget extends StatsOverviewWidget
{
    protected ?string $heading = '瀏覽器任務健康狀態';

    protected static ?int $sort = -20;

    /**
     * ⚠️ Filament 的 widget 預設 lazy（CanBeLazy::$isLazy = true），會先吐一個
     *    x-intersect 占位元素、等進入視窗才發第二個 Livewire 請求把內容抓回來。
     *
     *    這裡刻意關掉：
     *      1. 生產機是 1 CPU。每個 lazy widget 等於多一次完整的 Laravel bootstrap，
     *         那個成本遠大於這裡的幾個 COUNT 與一次 Redis 查詢。
     *      2. 「Mac 是不是離線」是打開 /admin 第一眼就要看到的資訊，
     *         不該等到捲動或第二個 round trip。
     */
    protected static bool $isLazy = false;

    /**
     * ⚠️ StatsOverviewWidget 用了 Concerns\CanPoll，getPollingInterval() 是真的存在的 API。
     *    （別跟 Filament 的 EditRecord 搞混 —— 那個沒有 polling。）
     *    30 秒：心跳門檻是 2 分鐘，輪詢比它快才看得到狀態變化；再快只是白打 Redis。
     */
    protected function getPollingInterval(): ?string
    {
        return '30s';
    }

    protected function getStats(): array
    {
        $health = app(BrowserHealth::class);

        return [
            $this->workerStat($health),
            $this->backlogStat($health),
            $this->usageStat($health),
            $this->stuckStat($health),
        ];
    }

    private function workerStat(BrowserHealth $health): Stat
    {
        if (! $health->workerAlive()) {
            $last = $health->lastHeartbeat();

            return Stat::make('Mac worker', '🔴 離線')
                ->description($last === null
                    ? '從未收到心跳。瀏覽器任務會留在佇列，Mac 開機後自動繼續。'
                    : sprintf(
                        '最後心跳 %d 分鐘前（%s）。瀏覽器任務會留在佇列，Mac 開機後自動繼續。',
                        (int) $health->heartbeatAgeMinutes(),
                        $last->setTimezone(config('services.browser.window.timezone', 'Asia/Taipei'))->format('m/d H:i'),
                    ))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger');
        }

        return Stat::make('Mac worker', '🟢 正常')
            ->description(sprintf(
                '最後心跳 %d 分鐘前%s',
                (int) $health->heartbeatAgeMinutes(),
                $health->workerHost() === null ? '' : '（' . $health->workerHost() . '）',
            ))
            ->descriptionIcon('heroicon-m-signal')
            ->color('success');
    }

    private function backlogStat(BrowserHealth $health): Stat
    {
        $backlog = $health->queueBacklog();
        $oldest = $health->oldestPendingMinutes();

        $parts = [];

        if ($oldest !== null) {
            $parts[] = "最舊已等 {$oldest} 分鐘";
        }

        // 不在執行時段時「積壓」是刻意的，不該讓 operator 以為壞了
        $parts[] = $health->withinWindow() ? '在執行時段內' : '目前不在 09:00–23:00 執行時段，已刻意暫緩';

        return Stat::make('browser 佇列積壓', (string) $backlog)
            ->description(implode('，', $parts))
            ->descriptionIcon('heroicon-m-queue-list')
            ->color($oldest !== null && $oldest >= BrowserHealth::BACKLOG_WARNING_MINUTES ? 'danger' : 'gray');
    }

    private function usageStat(BrowserHealth $health): Stat
    {
        $limits = $health->rateLimitRemaining();
        $scrape = $limits['shopee_scrape'];
        $used = max(0, $scrape['max'] - $scrape['remaining']);

        return Stat::make('本小時蝦皮抓取用量', "{$used} / {$scrape['max']}")
            ->description(collect($limits)
                ->except('shopee_scrape')
                ->map(fn (array $row) => "{$row['label']} 剩 {$row['remaining']}/{$row['max']}")
                ->implode('、'))
            ->descriptionIcon('heroicon-m-shield-check')
            // 剩餘 <= 20% 才轉色：上限是帳號風險控管，用完是正常的，不是故障
            ->color($scrape['remaining'] <= (int) ceil($scrape['max'] * 0.2) ? 'warning' : 'gray');
    }

    private function stuckStat(BrowserHealth $health): Stat
    {
        $needsManual = $health->needsManualTasks();
        $stale = $health->staleTasks();

        return Stat::make('卡住的任務', (string) $needsManual)
            ->description($stale > 0
                ? "需人工介入 {$needsManual} 筆，另有 {$stale} 筆 running 逾時待回收"
                : '需人工介入（人機驗證／登入失效／執行端中斷）')
            ->descriptionIcon('heroicon-m-hand-raised')
            ->color($needsManual > 0 || $stale > 0 ? 'danger' : 'success')
            ->url(BrowserTaskResource::getUrl('index', [
                'tableFilters' => ['status' => ['values' => [BrowserTaskStatus::NeedsManual->value]]],
            ]));
    }
}
