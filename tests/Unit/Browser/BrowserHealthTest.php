<?php

declare(strict_types=1);

use App\Enums\BrowserTaskStatus;
use App\Models\BrowserTask;
use App\Services\Browser\BrowserHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Cache::flush();
    $this->health = app(BrowserHealth::class);
});

/*
|--------------------------------------------------------------------------
| Worker 存活判定
|--------------------------------------------------------------------------
|
| 這是整個混合架構唯一的存活訊號。判錯的代價：
|   - 誤判死亡 → operator 跑去開機／重按重試，把 rate limit 用光
|   - 誤判存活 → 任務積著沒人處理，而 UI 顯示一切正常
*/

it('treats a recent heartbeat as alive', function () {
    Cache::put(BrowserHealth::HEARTBEAT_KEY, now()->subMinute()->toIso8601String(), 300);

    expect($this->health->workerAlive())->toBeTrue()
        ->and($this->health->heartbeatAgeMinutes())->toBe(1);
});

it('treats a stale heartbeat as dead', function () {
    // 門檻 120 秒：心跳每分鐘一次，容許漏掉一次。3 分鐘前就是真的沒人在跑了。
    Cache::put(BrowserHealth::HEARTBEAT_KEY, now()->subMinutes(3)->toIso8601String(), 300);

    expect($this->health->workerAlive())->toBeFalse()
        ->and($this->health->heartbeatAgeMinutes())->toBe(3);
});

it('treats a missing heartbeat as dead', function () {
    expect($this->health->workerAlive())->toBeFalse()
        ->and($this->health->lastHeartbeat())->toBeNull()
        ->and($this->health->heartbeatAgeMinutes())->toBeNull();
});

it('sits exactly on the alive threshold boundary', function () {
    // 邊界要明確：剛好 120 秒算活，121 秒算死。
    Cache::put(BrowserHealth::HEARTBEAT_KEY, now()->subSeconds(BrowserHealth::ALIVE_SECONDS)->toIso8601String(), 300);
    expect($this->health->workerAlive())->toBeTrue();

    Cache::put(BrowserHealth::HEARTBEAT_KEY, now()->subSeconds(BrowserHealth::ALIVE_SECONDS + 1)->toIso8601String(), 300);
    expect($this->health->workerAlive())->toBeFalse();
});

it('reads back the heartbeat machine name', function () {
    Cache::put(BrowserHealth::HEARTBEAT_HOST_KEY, 'paul-macbook', 300);

    expect($this->health->workerHost())->toBe('paul-macbook');
});

it('tolerates a heartbeat stored as a datetime object', function () {
    // 舊版本曾經直接 Cache::put(now())，serialize 的 cache 驅動讀回來是 Carbon 物件。
    Cache::put(BrowserHealth::HEARTBEAT_KEY, now()->subSeconds(30), 300);

    expect($this->health->workerAlive())->toBeTrue();
});

it('never reports a negative age when clocks disagree', function () {
    // 兩台機器的時鐘不會完全同步。Mac 快幾秒就會寫出「未來」的心跳，
    // 那時 UI 不該顯示 -1 分鐘。
    Cache::put(BrowserHealth::HEARTBEAT_KEY, now()->addSeconds(30)->toIso8601String(), 300);

    expect($this->health->heartbeatAgeMinutes())->toBe(0)
        ->and($this->health->workerAlive())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| 佇列積壓
|--------------------------------------------------------------------------
*/

it('reports zero backlog when the queue is empty', function () {
    expect($this->health->queueBacklog())->toBe(0);
});

it('counts jobs waiting on the browser queue', function () {
    Queue::fake();

    Queue::push('JobA', [], 'browser');
    Queue::push('JobB', [], 'browser');
    Queue::push('JobC', [], 'default');

    // 只數 browser queue —— default 上的生成類 job 跟 Mac 無關
    expect($this->health->queueBacklog())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| 最舊 pending 等待時間
|--------------------------------------------------------------------------
*/

it('has no oldest pending task when nothing is pending', function () {
    BrowserTask::factory()->succeeded()->create();

    expect($this->health->oldestPendingMinutes())->toBeNull();
});

it('reports how long the oldest pending task has waited', function () {
    BrowserTask::factory()->create(['queued_at' => now()->subMinutes(45)]);
    BrowserTask::factory()->create(['queued_at' => now()->subMinutes(5)]);
    // 已完成的不該影響「還在等」的統計
    BrowserTask::factory()->succeeded()->create(['queued_at' => now()->subHours(9)]);

    expect($this->health->oldestPendingMinutes())->toBe(45);
});

/*
|--------------------------------------------------------------------------
| Rate limit 剩餘
|--------------------------------------------------------------------------
*/

it('reports remaining rate limit per action', function () {
    config(['services.browser.rate_limits.shopee_scrape_per_hour' => 5]);

    $limiter = app(\App\Services\Browser\BrowserRateLimiter::class);
    $limiter->hit('shopee_scrape');
    $limiter->hit('shopee_scrape');

    $limits = $this->health->rateLimitRemaining();

    expect($limits['shopee_scrape']['max'])->toBe(5)
        ->and($limits['shopee_scrape']['remaining'])->toBe(3)
        ->and($limits)->toHaveKeys(['shopee_scrape', 'external_image', 'session_check']);
});

/*
|--------------------------------------------------------------------------
| 卡住與待人工
|--------------------------------------------------------------------------
*/

it('counts tasks stuck in running past the threshold', function () {
    BrowserTask::factory()->create([
        'status' => BrowserTaskStatus::Running,
        'started_at' => now()->subMinutes(31),
    ]);
    // 29 分鐘還在門檻內（最壞情況的正常執行就要十幾分鐘）
    BrowserTask::factory()->create([
        'status' => BrowserTaskStatus::Running,
        'started_at' => now()->subMinutes(29),
    ]);

    expect($this->health->staleTasks())->toBe(1);
});

it('counts tasks needing manual intervention', function () {
    BrowserTask::factory()->count(2)->needsManual()->create();
    BrowserTask::factory()->failed()->create();

    expect($this->health->needsManualTasks())->toBe(2);
});
