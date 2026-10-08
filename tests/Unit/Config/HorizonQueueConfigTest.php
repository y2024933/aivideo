<?php

declare(strict_types=1);

/**
 * Horizon 的 queue 分流守護測試。
 *
 * 這個檔案守的是兩個「改錯了不會壞在測試裡、只會壞在生產環境」的設定：
 *
 *   1. browser queue 必須有 supervisor 監聽。
 *      P5 留下的狀態是 supervisor-1 只吃 ['default']，所有 ->onQueue('browser')
 *      的 job 被推進 Redis 後「永遠沒有人取」—— 沒有 failed job、沒有 log、
 *      Horizon 上一片正常，只有商品永遠卡在 importing。
 *
 *   2. 每個 worker 的 timeout 必須小於 queue 的 retry_after。
 *      違反時 Redis queue 會在 worker 還在跑的時候重派同一個 job，
 *      對 browser queue 來說等於同一個 Chrome profile 被兩個 process 同時開 ——
 *      profile 損壞，修復代價是重新 SMS OTP 登入蝦皮。
 */
it('has a dedicated supervisor for the browser queue', function () {
    $browser = config('horizon.defaults.supervisor-browser');

    expect($browser)->toBeArray()
        ->and($browser['queue'])->toContain('browser')
        ->and($browser['connection'])->toBe('redis');
});

it('keeps browser concurrency at exactly one process', function () {
    // 同一個 Chrome user-data-dir 不能被多個 process 同時開啟（會損壞），
    // 併發抓取也是最明顯的機器人特徵。這不是效能設定。
    expect(config('horizon.defaults.supervisor-browser.maxProcesses'))->toBe(1);
})->group('browser-safety');

it('never lets any environment raise browser concurrency above one', function () {
    foreach ((array) config('horizon.environments') as $environment => $supervisors) {
        if (! array_key_exists('supervisor-browser', $supervisors)) {
            continue;
        }

        expect($supervisors['supervisor-browser']['maxProcesses'])
            ->toBeLessThanOrEqual(1, "{$environment} 的 supervisor-browser 併發超過 1");
    }
})->group('browser-safety');

it('does not retry browser jobs at the worker layer', function () {
    // 重試與 backoff 都在 PlaywrightBrowserAutomation 內部做（10/30/90 秒）。
    // 這一層再重試會讓單一商品打出十幾次請求、直接撞爆 rate limit。
    expect(config('horizon.defaults.supervisor-browser.tries'))->toBe(1);
});

it('covers every queue that jobs are dispatched to', function () {
    $covered = collect(config('horizon.defaults'))
        ->flatMap(fn (array $supervisor) => (array) ($supervisor['queue'] ?? []))
        ->unique()
        ->all();

    // default = 生成類 job（圖片／動畫／配音／剪接）
    // browser = ScrapeShopeeProductJob 等 ->onQueue('browser') 的 job
    expect($covered)->toContain('default')->toContain('browser');
});

/**
 * ⚠️ Laravel 的硬規則，不是風格偏好：
 *      worker timeout  <  queue retry_after
 *
 * retry_after 是「job 被取走後多久沒 ack 就重新派送」。timeout >= retry_after 時
 * 會出現「worker 還在跑，queue 已經把同一個 job 再派一次」。
 */
it('keeps every supervisor timeout below the queue retry_after', function () {
    $retryAfter = (int) config('queue.connections.redis.retry_after');

    expect($retryAfter)->toBeGreaterThan(0);

    $supervisors = collect(config('horizon.defaults'));

    // environments 的覆寫也要檢查 —— 只檢查 defaults 的話，
    // 有人在 production 那一組把 timeout 調大就溜過去了。
    foreach ((array) config('horizon.environments') as $environment => $overrides) {
        foreach ($overrides as $name => $options) {
            if (array_key_exists('timeout', $options)) {
                $supervisors->put("{$environment}/{$name}", $options);
            }
        }
    }

    $supervisors->each(function (array $options, string $name) use ($retryAfter) {
        expect((int) $options['timeout'])->toBeLessThan(
            $retryAfter,
            "{$name} 的 timeout 必須小於 queue.connections.redis.retry_after（{$retryAfter}），"
            . '否則 job 會在 worker 還在跑時被重複派送'
        );
    });
})->group('browser-safety');

it('matches the queue-browser container timeout to the supervisor timeout', function () {
    // docker-compose.yml 的 queue-browser 跑的是 queue:work --timeout=600。
    // 兩邊不一致時，實際生效的是誰取決於部署方式（Horizon vs queue:work），
    // 而「我改了 config 但沒生效」是最難查的那種問題。
    $compose = file_get_contents(base_path('docker-compose.yml'));

    expect($compose)->toContain('--queue=browser')
        ->and($compose)->toContain('--timeout=' . config('horizon.defaults.supervisor-browser.timeout'));
});

it('gives the browser queue a longer long-wait threshold', function () {
    // browser 任務本身就要 20–120 秒、maxProcesses=1、還會刻意等執行時段，
    // 用 default 的 60 秒門檻會讓 LongWaitDetected 一直誤報。
    expect((int) config('horizon.waits.redis:browser'))
        ->toBeGreaterThan((int) config('horizon.waits.redis:default'));
});

it('distinguishes the two machines in the horizon ui', function () {
    // 兩台機器把 supervisor 註冊到同一份 Redis，名稱相同就分不出誰是誰。
    expect(config('horizon.name'))->not->toBeNull();
});

// ─── P10 補的對照組：上面那條只驗 horizon.name 非 null，
//     兩台機器共用同一個寫死的字串也會綠，而那正是它要防的事。
it('derives the horizon name from the machine role so prod and mac differ', function () {
    $resolve = function (array $env) {
        $original = [];

        foreach ($env as $key => $value) {
            $original[$key] = getenv($key) === false ? null : getenv($key);
            $value === null ? putenv($key) : putenv("{$key}={$value}");
        }

        try {
            return (string) (require config_path('horizon.php'))['name'];
        } finally {
            foreach ($original as $key => $value) {
                $value === null ? putenv($key) : putenv("{$key}={$value}");
            }
        }
    };

    expect($resolve(['HORIZON_NAME' => null, 'APP_ROLE' => 'prod']))->toBe('prod')
        ->and($resolve(['HORIZON_NAME' => null, 'APP_ROLE' => 'mac']))->toBe('mac')
        // HORIZON_NAME 優先（容器 hostname 是隨機 hash，不能靠它分辨）
        ->and($resolve(['HORIZON_NAME' => 'mac-laptop', 'APP_ROLE' => 'prod']))->toBe('mac-laptop')
        // 兩台機器算出來的名稱必須不同，否則 Horizon UI 上分不出誰是誰
        ->and($resolve(['HORIZON_NAME' => null, 'APP_ROLE' => 'prod']))
        ->not->toBe($resolve(['HORIZON_NAME' => null, 'APP_ROLE' => 'mac']));
});
