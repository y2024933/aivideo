<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * 排程。
     *
     * ⚠️ 混合部署的關鍵前提：生產機與 Mac 共用同一份 MySQL 與 Redis（走 Tailscale）。
     *    兩台機器都跑 schedule:run 的話，每個排程都會「各跑一次」。所以凡是會改資料
     *    或有副作用的排程，一律用 config('app.role') gate 住，只讓一台負責。
     *
     *    role 來自 APP_ROLE（生產機 prod / Mac mac，見 config/app.php）。
     *    刻意不用 onOneServer()：那需要 cache lock，而「哪台跑」在這裡不是負載平衡
     *    問題而是能力問題 —— 心跳只有 Mac 回報得出來，reap-stale 只有生產機該判定。
     */
    protected function schedule(Schedule $schedule): void
    {
        $role = (string) config('app.role');

        if ($role === 'mac') {
            /*
             * Mac 的存活訊號。每分鐘一次，對應 BrowserHealth::ALIVE_SECONDS = 120
             * （容許漏一次）。
             *
             * ⚠️ 刻意不套用 09:00–23:00 的執行時段，也不加 jitter：
             *    這支指令只寫一個 cache key，不碰瀏覽器、不碰蝦皮，沒有任何反爬考量。
             *    反過來說，如果限制了時段，深夜看 /admin 的人會看到「Mac 離線」的紅燈，
             *    而那是假警報 —— 心跳必須 24 小時持續，否則就失去存在意義。
             */
            $schedule->command('browser:heartbeat')
                ->everyMinute()
                ->withoutOverlapping(5)
                ->runInBackground();
        }

        if ($role === 'prod') {
            /*
             * 回收卡死的瀏覽器任務（Mac 睡眠／crash 的善後）。
             *
             * ⚠️ 純 DB 操作，不碰瀏覽器 → 不受 09:00–23:00 時段限制，也不需要 jitter。
             *    反而必須全天候跑：Mac 半夜睡著留下的 running 任務，operator 早上
             *    打開 /admin 時就該已經是「需人工介入」而不是假裝還在跑。
             */
            $schedule->command('browser:reap-stale')
                ->everyTenMinutes()
                ->withoutOverlapping(10)
                ->runInBackground();
        }

        /*
         * ⚠️ 給未來新增「會真的操作瀏覽器 / 打蝦皮」的排程的規則（例如登入狀態巡檢、
         *    批次抓取）：
         *      1. 必須 ->between('09:00', '23:00')（台灣時間，見下方 timezone 註解），
         *         半夜連續請求是最明顯的機器人特徵
         *      2. 必須加 jitter（整點整分的規律請求同樣是特徵）。做法是在排程裡
         *         ->delay() 或在 job 內 ->delay(now()->addSeconds(random_int(0, 540)))
         *      3. 時段的「最終」把關在 BrowserRateLimiter::reasonToBlock()，排程層只是
         *         減少無謂的 dispatch —— 不要只靠排程層，手動觸發一樣要被擋
         *
         *    目前沒有這類排程（抓取都由 operator 貼連結觸發），所以這裡只留規則。
         */
    }

    /**
     * ⚠️ 排程的時區。Laravel 預設用 app.timezone（UTC），但所有反爬時段都是以台灣時間
     *    定義的。UTC 的 09:00 是台灣的 17:00，差 8 小時 —— 這種錯會讓「只在白天抓」
     *    變成「只在半夜抓」，剛好是最糟的結果。
     */
    protected function scheduleTimezone(): string
    {
        return (string) config('services.browser.window.timezone', 'Asia/Taipei');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
