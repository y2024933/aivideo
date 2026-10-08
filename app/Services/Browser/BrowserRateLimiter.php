<?php

declare(strict_types=1);

namespace App\Services\Browser;

use App\Exceptions\BrowserRateLimitException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;

/**
 * 瀏覽器任務的節流與執行時段守門員。
 *
 * 刻意走 Laravel RateLimiter（底層是 cache store，生產設 redis）而不是記在記憶體或
 * DB count：
 *   1. queue worker 重啟不會把計數歸零 —— 用記憶體等於「重啟就能無限抓」
 *   2. app 與 queue-browser 兩個容器共享同一份計數
 *   3. 固定視窗自動過期，不用寫清理排程
 *
 * 被擋時一律回傳／丟出「人看得懂的繁中原因」。silent fail 的代價是 operator 以為
 * 系統壞了，然後手動重按十次 —— 那才是真的會把帳號玩掉。
 */
final class BrowserRateLimiter
{
    private const PREFIX = 'browser-rl:';

    private const WINDOW_SECONDS = 3600;

    /**
     * 可否執行。
     *
     * @param  array{ignore_time_window?: bool}  $options  ignore_time_window 只給 Filament 的
     *                                                     人工重試用（人在電腦前面，風險自己承擔）
     * @return string|null null = 放行；字串 = 繁中拒絕原因
     */
    public function reasonToBlock(string $action, array $options = []): ?string
    {
        if (! ($options['ignore_time_window'] ?? false) && ! $this->withinWindow()) {
            [$start, $end, $tz] = $this->window();

            return sprintf(
                '目前 %s（%s）不在允許的執行時段 %s–%s，已暫緩以免被判定為機器人。需要立刻執行請用「忽略時段限制」重試。',
                $this->now()->format('H:i'),
                $tz,
                $start,
                $end,
            );
        }

        $max = $this->maxPerHour($action);

        if (RateLimiter::tooManyAttempts($this->key($action), $max)) {
            return sprintf(
                '已達「%s」的每小時上限 %d 次，請於 %d 秒後再試（這道限制是為了避免帳號被風控，請勿調高）。',
                $action,
                $max,
                RateLimiter::availableIn($this->key($action)),
            );
        }

        return null;
    }

    /** 檢查並記一次用量；被擋時丟例外 */
    public function consume(string $action, array $options = []): void
    {
        if ($reason = $this->reasonToBlock($action, $options)) {
            throw new BrowserRateLimitException($reason);
        }

        $this->hit($action);
    }

    /**
     * 記一次用量。
     *
     * 刻意在「送出請求前」呼叫而不是成功後：失敗的請求同樣打到了蝦皮，
     * 只算成功次數等於失敗時可以無限重試。
     */
    public function hit(string $action): void
    {
        RateLimiter::hit($this->key($action), self::WINDOW_SECONDS);
    }

    public function remaining(string $action): int
    {
        return RateLimiter::remaining($this->key($action), $this->maxPerHour($action));
    }

    public function clear(string $action): void
    {
        RateLimiter::clear($this->key($action));
    }

    public function withinWindow(): bool
    {
        [$start, $end] = $this->window();
        $now = $this->now()->format('H:i');

        // start > end 代表跨午夜（例：22:00–02:00），條件要改成 or
        return $start <= $end
            ? $now >= $start && $now < $end
            : $now >= $start || $now < $end;
    }

    /** @return array{0: string, 1: string, 2: string} start / end / timezone */
    private function window(): array
    {
        return [
            (string) config('services.browser.window.start', '09:00'),
            (string) config('services.browser.window.end', '23:00'),
            (string) config('services.browser.window.timezone', 'Asia/Taipei'),
        ];
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now()->setTimezone($this->window()[2]);
    }

    private function maxPerHour(string $action): int
    {
        return (int) config("services.browser.rate_limits.{$action}_per_hour", 20);
    }

    private function key(string $action): string
    {
        return self::PREFIX . $action;
    }
}
