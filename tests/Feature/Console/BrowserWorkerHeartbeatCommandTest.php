<?php

declare(strict_types=1);

use App\Services\Browser\BrowserHealth;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => Cache::flush());

it('writes a heartbeat that the production machine can read', function () {
    $this->artisan('browser:heartbeat')->assertSuccessful();

    expect(app(BrowserHealth::class)->workerAlive())->toBeTrue()
        ->and(Cache::get(BrowserHealth::HEARTBEAT_KEY))->not->toBeNull()
        ->and(Cache::get(BrowserHealth::HEARTBEAT_HOST_KEY))->not->toBeNull();
});

it('is schedulable only on the mac role', function () {
    // ⚠️ 兩台機器共用同一份 Redis 與 MySQL，所以排程會在兩邊各跑一次。
    //    心跳只有 Mac 回報得出來 —— 生產機跑的話會讓 widget 永遠顯示綠燈，
    //    而那正好是這整套監控存在的理由被抵銷掉。
    $commandsFor = function (string $role): array {
        config(['app.role' => $role]);

        $schedule = new Illuminate\Console\Scheduling\Schedule;
        (new ReflectionMethod(App\Console\Kernel::class, 'schedule'))
            ->invoke(app(App\Console\Kernel::class), $schedule);

        return array_map(fn ($event) => $event->command, $schedule->events());
    };

    expect(implode(' ', $commandsFor('mac')))
        ->toContain('browser:heartbeat')
        ->not->toContain('browser:reap-stale');

    expect(implode(' ', $commandsFor('prod')))
        ->toContain('browser:reap-stale')
        ->not->toContain('browser:heartbeat');
});

it('runs the browser schedules in taipei time', function () {
    // ⚠️ app.timezone 是 UTC，但所有反爬時段都是以台灣時間定義的。
    //    差 8 小時會讓「只在白天抓」變成「只在半夜抓」—— 剛好是最糟的結果。
    $timezone = (new ReflectionMethod(App\Console\Kernel::class, 'scheduleTimezone'))
        ->invoke(app(App\Console\Kernel::class));

    expect($timezone)->toBe('Asia/Taipei');
});
