<?php

declare(strict_types=1);

use App\Data\Browser\BrowserResult;
use App\Data\ShopeeItemRef;
use App\Enums\BrowserStrategy;
use App\Enums\BrowserTaskStatus;
use App\Enums\BrowserTaskType;
use App\Models\BrowserTask;
use App\Models\Product;
use App\Services\Contracts\BrowserAutomationContract;
use App\Services\Stubs\StubBrowserAutomation;
use Illuminate\Support\Facades\Http;

/*
 * Stub 的零網路保證。
 *
 * 這是整個測試套件最重要的一條：其他 stub 守的是「不要花錢」，這顆守的是
 * 「不要被封帳號」。付費 API 被誤打最多損失幾塊美金；蝦皮被誤打會封帳號，
 * 而帳號是人工 SMS OTP 登入的，沒有任何程式化的恢復方式。
 */

beforeEach(function () {
    // 任何漏出去的請求都會變成 assertNothingSent 失敗，而不是真的送出
    Http::fake();
    $this->ref = new ShopeeItemRef(111, 222, 'https://example.test/product/111/222');
});

it('容器在測試環境一律綁 stub，不會綁真實的 Playwright 實作', function () {
    expect(config('services.use_real_apis'))->toBeFalse()
        ->and(app(BrowserAutomationContract::class))->toBeInstanceOf(StubBrowserAutomation::class);
});

it('stub 的三個方法都不發任何 HTTP 請求', function () {
    $stub = new StubBrowserAutomation();

    $stub->scrapeShopeeProduct($this->ref);
    $stub->fetchImagesFromUrl('https://example.test/page');
    $stub->checkSessions(['shopee-seller']);

    Http::assertNothingSent();
});

it('預設情境是 xhr 成功，並把 get_pc 形狀的假資料放在 data.raw', function () {
    $result = (new StubBrowserAutomation())->scrapeShopeeProduct($this->ref);

    expect($result->succeeded())->toBeTrue()
        ->and($result->usable())->toBeTrue()
        ->and($result->isDegraded())->toBeFalse()
        ->and($result->strategy)->toBe(BrowserStrategy::Xhr->value)
        // 價格刻意保留 ×100000 的原始單位，否則 mapper 的除法在整條鏈上永遠沒測到
        ->and(data_get($result->data, 'raw.data.item.price'))->toBe(129000000);

    Http::assertNothingSent();
});

it('可以注入降級情境，回傳 dom 形狀且 usable 但不 succeeded', function () {
    $result = (new StubBrowserAutomation())->respondWith(BrowserResult::DEGRADED)->scrapeShopeeProduct($this->ref);

    expect($result->succeeded())->toBeFalse()
        ->and($result->usable())->toBeTrue()
        ->and($result->isDegraded())->toBeTrue()
        ->and($result->strategy)->toBe(BrowserStrategy::Dom->value)
        ->and(data_get($result->data, 'dom.title'))->toBeString();

    Http::assertNothingSent();
});

it('可以注入失敗情境', function () {
    $result = (new StubBrowserAutomation())
        ->respondWith(BrowserResult::FAILED, errorCode: 'timeout', errorMessage: '等待 get_pc 逾時')
        ->scrapeShopeeProduct($this->ref);

    expect($result->usable())->toBeFalse()
        ->and($result->needsManual())->toBeFalse()
        ->and($result->errorCode)->toBe('timeout')
        ->and($result->errorMessage)->toBe('等待 get_pc 逾時');

    Http::assertNothingSent();
});

it('可以注入 needs_manual 情境', function () {
    $result = (new StubBrowserAutomation())
        ->respondWith(BrowserResult::NEEDS_MANUAL, errorCode: 'captcha', errorMessage: '觸發人機驗證')
        ->scrapeShopeeProduct($this->ref);

    expect($result->needsManual())->toBeTrue()
        ->and($result->usable())->toBeFalse()
        ->and($result->taskStatus())->toBe(BrowserTaskStatus::NeedsManual);

    Http::assertNothingSent();
});

it('stub 跟真實實作一樣會寫 browser_tasks（否則稽核紀錄只在正式環境才會出現）', function () {
    $product = Product::factory()->shopee(111, 222)->create();

    (new StubBrowserAutomation())->scrapeShopeeProduct($this->ref, ['subject' => $product]);

    $task = BrowserTask::sole();

    expect($task->type)->toBe(BrowserTaskType::ShopeeScrapeProduct)
        ->and($task->status)->toBe(BrowserTaskStatus::Succeeded)
        ->and($task->strategy_used)->toBe(BrowserStrategy::Xhr)
        ->and($task->attempts)->toBe(1)
        ->and($task->subject_id)->toBe($product->id)
        ->and($task->payload)->toMatchArray(['shop_id' => 111, 'item_id' => 222])
        ->and($task->duration_ms)->toBeGreaterThan(0)
        ->and($task->finished_at)->not->toBeNull();

    Http::assertNothingSent();
});

it('降級與失敗也都寫進 browser_tasks', function () {
    (new StubBrowserAutomation())->respondWith(BrowserResult::DEGRADED)->scrapeShopeeProduct($this->ref);
    (new StubBrowserAutomation())->respondWith(BrowserResult::FAILED)->scrapeShopeeProduct($this->ref);

    expect(BrowserTask::where('status', BrowserTaskStatus::Degraded)->count())->toBe(1)
        ->and(BrowserTask::where('status', BrowserTaskStatus::Failed)->count())->toBe(1)
        ->and(BrowserTask::where('status', BrowserTaskStatus::Degraded)->value('strategy_used'))->toBe(BrowserStrategy::Dom);

    Http::assertNothingSent();
});

it('外站圖片與 session 檢查也回得出資料且記下呼叫', function () {
    $stub = new StubBrowserAutomation();

    expect($stub->fetchImagesFromUrl('https://example.test/page')->data['images'])->toHaveCount(2)
        ->and($stub->checkSessions(['shopee-seller'])->data['sessions'][0]['profile'])->toBe('shopee-seller')
        ->and(array_column($stub->calls, 'method'))->toBe(['fetchImagesFromUrl', 'checkSessions']);

    Http::assertNothingSent();
});

it('fixture 是手寫假資料，不含任何真實蝦皮網域', function () {
    // 「為了更新 fixture 去抓一次蝦皮」是這個專案最不該發生的事
    $fixture = json_encode(StubBrowserAutomation::shopeeFixture(), JSON_UNESCAPED_UNICODE);

    expect($fixture)->not->toContain('susercontent')
        ->and($fixture)->toContain('stubhash');
});
