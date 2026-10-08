<?php

declare(strict_types=1);

use App\Data\Browser\BrowserResult;
use App\Enums\BrowserStrategy;
use App\Enums\BrowserTaskStatus;
use App\Enums\BrowserTaskType;
use App\Enums\ProductStatus;
use App\Jobs\DownloadProductImagesJob;
use App\Jobs\ScrapeShopeeProductJob;
use App\Models\BrowserTask;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Contracts\BrowserAutomationContract;
use App\Services\Stubs\StubBrowserAutomation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 * 抓取 Job 的四條路徑 + browser_tasks 稽核 + rate limit + 降級不自動放行。
 *
 * ⚠️ 全程走 StubBrowserAutomation：零網路、零瀏覽器、零蝦皮請求。
 *    這裡若不小心綁到真實實作，代價是帳號被封，而帳號只能人工 SMS OTP 恢復。
 */

function fakeProductPng(int $width = 1000, int $height = 1000): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

/**
 * 直接呼叫 handle()，不經 dispatchSync。
 *
 * ⚠️ 不能用 dispatchSync：Illuminate\Bus\Dispatcher::dispatchSync() 對 ShouldQueue
 *    的 job 會轉成 dispatchToQueue($job->onConnection('sync'))，而 Queue::fake() 之下
 *    那條路只會「記錄 push」而不執行 —— job 看起來跑了，其實什麼都沒做。
 */
function runScrape(string $productId, bool $ignoreTimeWindow = false): void
{
    app()->call([new ScrapeShopeeProductJob($productId, $ignoreTimeWindow), 'handle']);
}

/** 把指定情境的 stub 綁進容器並回傳它 */
function bindBrowserStub(string $status = BrowserResult::SUCCEEDED, ?array $data = null, ?string $errorCode = null, ?string $errorMessage = null): StubBrowserAutomation
{
    $stub = (new StubBrowserAutomation())->respondWith($status, $data, errorCode: $errorCode, errorMessage: $errorMessage);
    app()->instance(BrowserAutomationContract::class, $stub);

    return $stub;
}

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('s3');

    config([
        'cache.default' => 'array',
        // 預設關掉 autopilot，單獨測放行行為時再開
        'video.autopilot.product' => false,
        'services.browser.rate_limits.shopee_scrape_per_hour' => 20,
        'services.browser.window.start' => '09:00',
        'services.browser.window.end' => '23:00',
        'services.browser.window.timezone' => 'Asia/Taipei',
        // 刻意改成測試網域：真實 CDN 連「出現在測試裡」都不需要
        'services.shopee.image_cdn' => 'https://cdn.example.test/file/',
    ]);

    cache()->store('array')->clear();
    // 固定在時段內，否則測試在半夜跑就會紅
    Carbon::setTestNow(Carbon::parse('2026-10-07 14:00:00', 'Asia/Taipei'));

    Http::fake(['https://cdn.example.test/*' => Http::response(fakeProductPng(), 200, ['Content-Type' => 'image/png'])]);

    $this->product = Product::factory()->shopee(111, 222)->create([
        'title' => '蝦皮商品 222（待補資料）',
        'status' => ProductStatus::Draft,
        'price' => null,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('xhr 成功：映射全欄位、下載圖片、轉到 checkpoint ①', function () {
    bindBrowserStub(BrowserResult::SUCCEEDED);

    runScrape($this->product->id);
    $product = $this->product->refresh();

    expect($product->status)->toBe(ProductStatus::ProductPendingReview)
        ->and($product->title)->toBe('無線藍牙耳機 ANC主動降噪 續航32小時')
        // ⚠️ 129000000 / 100000 = 1290.00
        ->and((float) $product->price)->toBe(1290.0)
        ->and((float) $product->price_before_discount)->toBe(1990.0)
        ->and((float) $product->rating_star)->toBe(4.83)
        ->and($product->rating_count)->toBe(1234)
        ->and($product->historical_sold)->toBe(5678)
        ->and($product->category)->toBe('耳機')
        ->and($product->category_path)->toBe(['手機平板與周邊', '耳機'])
        ->and($product->status_message)->toBeNull()
        ->and($product->scraped_at)->not->toBeNull()
        // raw_payload 保住原始回應，蝦皮改版時不用重爬
        ->and(data_get($product->raw_payload, 'data.item.price'))->toBe(129000000);
});

it('xhr 成功後圖片下載帶齊 metadata 與 S3 副本', function () {
    bindBrowserStub(BrowserResult::SUCCEEDED);

    runScrape($this->product->id);
    $images = $this->product->refresh()->images()->get();

    expect($images)->toHaveCount(3);

    $images->each(function (ProductImage $image) {
        expect($image->status)->toBe('done')
            // Remotion Lambda 只讀 remote_url，缺它渲染必 404
            ->and($image->remote_url)->not->toBeNull()
            ->and($image->width)->toBe(1000)
            ->and($image->height)->toBe(1000)
            ->and($image->bytes)->toBeGreaterThan(0)
            ->and($image->mime)->toBe('image/png')
            // 蝦皮商品圖的著作權在賣家，未取得授權前一律 unverified
            ->and($image->license_status)->toBe('unverified');
    });

    expect($images->where('is_primary', true))->toHaveCount(1);
});

it('圖片下載走 default queue，不佔用併發 1 的 browser queue', function () {
    Queue::fake();
    bindBrowserStub(BrowserResult::SUCCEEDED);

    runScrape($this->product->id);

    Queue::assertPushed(DownloadProductImagesJob::class, fn (DownloadProductImagesJob $job) => $job->queue === 'default');
});

it('降級抓取：仍成功進 checkpoint ①，但 status_message 註明要人工核對', function () {
    bindBrowserStub(BrowserResult::DEGRADED);

    runScrape($this->product->id);
    $product = $this->product->refresh();

    expect($product->status)->toBe(ProductStatus::ProductPendingReview)
        ->and($product->title)->toBe('無線藍牙耳機 ANC主動降噪 續航32小時')
        ->and((float) $product->price)->toBe(1290.0)
        ->and($product->status_message)->toContain('降級抓取')
        ->and($product->status_message)->toContain('人工核對')
        // DOM 撈不到評分與銷量，這正是不可自動放行的原因
        ->and($product->rating_star)->toBeNull()
        ->and($product->historical_sold)->toBeNull()
        ->and($product->hasDegradedScrape())->toBeTrue();
});

it('失敗：轉 import_failed 並留下繁中原因', function () {
    bindBrowserStub(BrowserResult::FAILED, errorCode: 'timeout', errorMessage: '等待 get_pc 逾時');

    runScrape($this->product->id);
    $product = $this->product->refresh();

    expect($product->status)->toBe(ProductStatus::ImportFailed)
        ->and($product->status_message)->toContain('timeout')
        ->and($product->status_message)->toContain('等待 get_pc 逾時')
        ->and($product->images()->count())->toBe(0);
});

it('needs_manual：轉 needs_manual 並寫 needs_manual_reason', function () {
    bindBrowserStub(BrowserResult::NEEDS_MANUAL, errorCode: 'verification_required', errorMessage: '被導向人機驗證頁');

    runScrape($this->product->id);
    $product = $this->product->refresh();

    expect($product->status)->toBe(ProductStatus::NeedsManual)
        ->and($product->needs_manual_reason)->toContain('被導向人機驗證頁')
        ->and($product->needs_manual_reason)->toContain('瀏覽器任務');
});

it('每條路徑都寫進 browser_tasks', function (string $status, BrowserTaskStatus $expected, ?BrowserStrategy $strategy) {
    bindBrowserStub($status);

    runScrape($this->product->id);
    $task = BrowserTask::sole();

    expect($task->type)->toBe(BrowserTaskType::ShopeeScrapeProduct)
        ->and($task->status)->toBe($expected)
        ->and($task->strategy_used)->toBe($strategy)
        ->and($task->subject_id)->toBe($this->product->id)
        ->and($task->subject_type)->toBe($this->product->getMorphClass())
        ->and($task->attempts)->toBe(1)
        ->and($task->finished_at)->not->toBeNull();
})->with([
    'xhr 成功' => [BrowserResult::SUCCEEDED, BrowserTaskStatus::Succeeded, BrowserStrategy::Xhr],
    'dom 降級' => [BrowserResult::DEGRADED, BrowserTaskStatus::Degraded, BrowserStrategy::Dom],
    '失敗' => [BrowserResult::FAILED, BrowserTaskStatus::Failed, null],
    '需人工' => [BrowserResult::NEEDS_MANUAL, BrowserTaskStatus::NeedsManual, null],
]);

it('超過每小時上限時完全不開瀏覽器，直接 import_failed', function () {
    config(['services.browser.rate_limits.shopee_scrape_per_hour' => 2]);
    $stub = bindBrowserStub(BrowserResult::SUCCEEDED);

    foreach ([211, 212, 213] as $index => $shopId) {
        $product = Product::factory()->shopee($shopId, 222)->create(['status' => ProductStatus::Draft]);
        runScrape($product->id);

        expect($product->refresh()->status)->toBe($index < 2 ? ProductStatus::ProductPendingReview : ProductStatus::ImportFailed);
    }

    // 第三個商品根本沒呼叫 contract —— 被擋就不該有任何請求打出去
    expect($stub->calls)->toHaveCount(2)
        ->and(Product::where('shopee_shop_id', 213)->value('status_message'))->toContain('每小時上限 2 次');
});

it('執行時段外被擋，但人工重試可以忽略時段', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-07 03:00:00', 'Asia/Taipei'));
    bindBrowserStub(BrowserResult::SUCCEEDED);

    runScrape($this->product->id);

    expect($this->product->refresh()->status)->toBe(ProductStatus::ImportFailed)
        ->and($this->product->status_message)->toContain('不在允許的執行時段');

    // import_failed → importing 是合法轉移，人工重試帶 ignoreTimeWindow
    runScrape($this->product->id, true);

    expect($this->product->refresh()->status)->toBe(ProductStatus::ProductPendingReview);
});

it('⚠️ 降級抓取絕不自動放行 checkpoint ①', function () {
    // 資料來源不可靠時一定要人看 —— ① 之後就開始花錢寫稿、生素材、渲染
    config(['video.autopilot.product' => true]);
    bindBrowserStub(BrowserResult::DEGRADED);

    runScrape($this->product->id);

    expect($this->product->refresh()->status)->toBe(ProductStatus::ProductPendingReview)
        ->and($this->product->statusHistory()->pluck('to_status')->all())->not->toContain('product_approved');
});

it('xhr 成功且資料齊全時 autopilot 才會放行 ①', function () {
    // 這條是上一條的對照組：證明 gate 真的會放行，所以「降級不放行」不是因為別的原因壞了
    Queue::fake();
    config(['video.autopilot.product' => true]);
    bindBrowserStub(BrowserResult::SUCCEEDED);
    ProductImage::factory()->count(2)->for($this->product)->create();

    runScrape($this->product->id);
    // 圖片 job 被 fake 了，手動跑一次讓它走到 Pipeline 的放行判斷
    app()->call([new DownloadProductImagesJob($this->product->id, []), 'handle']);

    expect($this->product->refresh()->status)->toBe(ProductStatus::ProductApproved)
        ->and($this->product->statusHistory()->where('to_status', 'product_approved')->value('triggered_by'))->toBe('autopilot');
});

it('沒有 shopee item id 的商品直接失敗，不浪費一次配額', function () {
    $stub = bindBrowserStub(BrowserResult::SUCCEEDED);
    $product = Product::factory()->create(['status' => ProductStatus::Draft]);

    runScrape($product->id);

    expect($product->refresh()->status)->toBe(ProductStatus::ImportFailed)
        ->and($product->status_message)->toContain('沒有蝦皮')
        ->and($stub->calls)->toBe([]);
});

it('狀態不在可抓取清單時直接跳過', function () {
    $stub = bindBrowserStub(BrowserResult::SUCCEEDED);
    $product = Product::factory()->shopee(999, 888)->status(ProductStatus::Rendering)->create();

    runScrape($product->id);

    expect($product->refresh()->status)->toBe(ProductStatus::Rendering)
        ->and($stub->calls)->toBe([])
        ->and(BrowserTask::count())->toBe(0);
});

it('job 的 tries 固定為 1：重試與 backoff 都在 PlaywrightBrowserAutomation 內部做', function () {
    // 雙重重試會讓單一商品打出 16 次請求，rate limit 會被自己的重試吃光
    $job = new ScrapeShopeeProductJob('x');

    expect($job->tries)->toBe(1)
        ->and($job->queue)->toBe('browser');
});
