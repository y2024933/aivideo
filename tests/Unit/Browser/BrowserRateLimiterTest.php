<?php

declare(strict_types=1);

use App\Exceptions\BrowserRateLimitException;
use App\Services\Browser\BrowserRateLimiter;
use Illuminate\Support\Carbon;

/**
 * 節流與執行時段。
 *
 * 這組測試守的是「帳號安全」而不是功能正確性：蝦皮封的是帳號，而帳號是人工
 * SMS OTP 登入的，封掉就沒有程式化的補救方式。所以上限被繞過等於整個專案停擺。
 *
 * 刻意用 array cache driver：測試不該依賴 Redis 有沒有開。
 */
beforeEach(function () {
    config([
        'cache.default' => 'array',
        'services.browser.rate_limits.shopee_scrape_per_hour' => 20,
        'services.browser.window.start' => '09:00',
        'services.browser.window.end' => '23:00',
        'services.browser.window.timezone' => 'Asia/Taipei',
    ]);

    cache()->store('array')->clear();

    // 固定在台灣時間 14:00（時段內），避免測試在半夜跑就紅
    Carbon::setTestNow(Carbon::parse('2026-10-07 14:00:00', 'Asia/Taipei'));

    $this->limiter = new BrowserRateLimiter();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('lets the first 20 scrapes through and blocks the 21st', function () {
    foreach (range(1, 20) as $i) {
        expect($this->limiter->reasonToBlock('shopee_scrape'))->toBeNull();
        $this->limiter->hit('shopee_scrape');
    }

    expect($this->limiter->reasonToBlock('shopee_scrape'))
        ->toBeString()
        ->toContain('每小時上限 20 次');
});

it('reports the remaining quota', function () {
    expect($this->limiter->remaining('shopee_scrape'))->toBe(20);

    $this->limiter->hit('shopee_scrape');
    $this->limiter->hit('shopee_scrape');

    expect($this->limiter->remaining('shopee_scrape'))->toBe(18);
});

it('reads the limit from config instead of hard coding 20', function () {
    config(['services.browser.rate_limits.shopee_scrape_per_hour' => 2]);

    $this->limiter->hit('shopee_scrape');
    $this->limiter->hit('shopee_scrape');

    expect($this->limiter->reasonToBlock('shopee_scrape'))->toContain('每小時上限 2 次');
});

it('refuses to run outside the taiwan time window', function (string $localTime) {
    Carbon::setTestNow(Carbon::parse("2026-10-07 {$localTime}", 'Asia/Taipei'));

    expect($this->limiter->withinWindow())->toBeFalse()
        ->and($this->limiter->reasonToBlock('shopee_scrape'))->toContain('不在允許的執行時段');
})->with(['03:00', '08:59', '23:00', '23:30']);

it('runs inside the taiwan time window', function (string $localTime) {
    Carbon::setTestNow(Carbon::parse("2026-10-07 {$localTime}", 'Asia/Taipei'));

    expect($this->limiter->withinWindow())->toBeTrue()
        ->and($this->limiter->reasonToBlock('shopee_scrape'))->toBeNull();
})->with(['09:00', '14:00', '22:59']);

it('judges the window in taipei time, not utc', function () {
    // UTC 01:00 = 台北 09:00 → 應該放行。用 UTC 判斷的話會誤擋。
    Carbon::setTestNow(Carbon::parse('2026-10-07 01:00:00', 'UTC'));

    expect($this->limiter->withinWindow())->toBeTrue();

    // UTC 14:00 = 台北 22:00（仍在窗內）；若誤用 UTC 會判定 14:00 在窗內也剛好為真，
    // 所以再測一個只有時區正確才會過的點：UTC 23:30 = 台北隔日 07:30 → 窗外
    Carbon::setTestNow(Carbon::parse('2026-10-07 23:30:00', 'UTC'));

    expect($this->limiter->withinWindow())->toBeFalse();
});

it('can bypass the time window with ignore_time_window', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-07 03:00:00', 'Asia/Taipei'));

    expect($this->limiter->reasonToBlock('shopee_scrape'))->toContain('不在允許的執行時段')
        ->and($this->limiter->reasonToBlock('shopee_scrape', ['ignore_time_window' => true]))->toBeNull();
});

it('never lets ignore_time_window bypass the hourly quota', function () {
    // 時段限制是禮貌，次數上限是安全線 —— 人工重試可以繞前者，絕不可繞後者
    Carbon::setTestNow(Carbon::parse('2026-10-07 03:00:00', 'Asia/Taipei'));
    config(['services.browser.rate_limits.shopee_scrape_per_hour' => 1]);

    $this->limiter->hit('shopee_scrape');

    expect($this->limiter->reasonToBlock('shopee_scrape', ['ignore_time_window' => true]))
        ->toContain('每小時上限 1 次');
});

it('throws a chinese reason from consume', function () {
    config(['services.browser.rate_limits.shopee_scrape_per_hour' => 1]);

    $this->limiter->consume('shopee_scrape');

    expect(fn () => $this->limiter->consume('shopee_scrape'))
        ->toThrow(BrowserRateLimitException::class, '每小時上限');
});

it('keeps separate counters per action', function () {
    config(['services.browser.rate_limits.external_image_per_hour' => 60]);

    foreach (range(1, 20) as $ignored) {
        $this->limiter->hit('shopee_scrape');
    }

    expect($this->limiter->reasonToBlock('shopee_scrape'))->toContain('每小時上限')
        ->and($this->limiter->reasonToBlock('external_image'))->toBeNull();
});

it('supports a window that wraps past midnight', function () {
    config(['services.browser.window.start' => '22:00', 'services.browser.window.end' => '02:00']);

    Carbon::setTestNow(Carbon::parse('2026-10-07 23:30:00', 'Asia/Taipei'));
    expect($this->limiter->withinWindow())->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2026-10-07 01:30:00', 'Asia/Taipei'));
    expect($this->limiter->withinWindow())->toBeTrue();

    Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00', 'Asia/Taipei'));
    expect($this->limiter->withinWindow())->toBeFalse();
});
