<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Browser\BrowserHealth;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Mac 端的「我還活著」心跳。
 *
 * 這是混合部署唯一的存活訊號。生產機看不到 Mac：Mac 沒有對外 IP、會睡眠、會關機、
 * 會換網路（咖啡廳 Wi-Fi → Tailscale 重新打洞）。沒有心跳的話，生產機無法區分
 *   (a) browser queue 空的（正常）
 *   (b) 任務積著但 Mac 離線（要去開機）
 *   (c) 任務積著且 Mac 在線但卡住（要去看 trace）
 * 這三種在 UI 上長得完全一樣，而處理方式完全不同。
 *
 * ⚠️ 必須寫進共用的 Redis（Mac 的 CACHE_STORE=redis 且 REDIS_HOST 指向生產機的
 *    Tailscale IP），寫到本機 file cache 的話生產機永遠讀不到。
 */
final class BrowserWorkerHeartbeatCommand extends Command
{
    protected $signature = 'browser:heartbeat';

    protected $description = '寫入 browser worker 心跳（只該在 Mac 端跑）';

    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $host = $this->machineName();

        Cache::put(BrowserHealth::HEARTBEAT_KEY, $now->toIso8601String(), BrowserHealth::HEARTBEAT_TTL_SECONDS);
        Cache::put(BrowserHealth::HEARTBEAT_HOST_KEY, $host, BrowserHealth::HEARTBEAT_TTL_SECONDS);

        $this->info("心跳已寫入：{$host} @ {$now->toDateTimeString()}");

        return self::SUCCESS;
    }

    /**
     * 機器名稱。
     *
     * 優先用 BROWSER_WORKER_NAME —— 容器的 hostname 是隨機 hash，顯示在 Filament 上
     * 完全看不出是哪台機器。退而求其次才用 gethostname()。
     *
     * ⚠️ 走 config() 不走 env()：config:cache 之後 env() 一律回 null。
     */
    private function machineName(): string
    {
        return (string) (config('services.browser.worker_name')
            ?: config('app.role') . '/' . (gethostname() ?: 'unknown'));
    }
}
