<?php

declare(strict_types=1);

use App\Data\Browser\BrowserResult;
use App\Data\ShopeeItemRef;
use App\Enums\BrowserStrategy;
use App\Enums\BrowserTaskStatus;
use App\Models\BrowserTask;
use App\Models\Product;
use App\Services\Browser\PlaywrightBrowserAutomation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
 * 真實實作的 HTTP 行為。
 *
 * ⚠️ 全部走 Http::fake —— 被 fake 的是「我們自己的 Fastify 服務」，不是蝦皮。
 *    這裡不會啟動任何瀏覽器，也不會有任何封包離開測試程序。
 */

beforeEach(function () {
    Storage::fake('s3');

    config([
        'services.browser.base_url' => 'http://browser.test:3031',
        'services.browser.token' => 'test-token',
        // ⚠️ backoff 設 0，否則這個測試會真的睡 130 秒
        'services.browser.retry.delays' => '0,0,0',
        'services.browser.artifacts_disk' => 's3',
    ]);

    $this->ref = new ShopeeItemRef(111, 222, 'https://shop.example.test/product/111/222');
    $this->client = app(PlaywrightBrowserAutomation::class);
});

it('沒設定 base_url 時立刻失敗，不會卡在 queue 裡等逾時', function () {
    // 生產機刻意留空 BROWSER_SERVICE_URL，誤派的 job 要馬上死掉
    config(['services.browser.base_url' => null]);
    Http::fake();

    $result = $this->client->scrapeShopeeProduct($this->ref);

    expect($result->status)->toBe(BrowserResult::FAILED)
        ->and($result->errorCode)->toBe('browser_service_not_configured')
        ->and($result->errorMessage)->toContain('BROWSER_SERVICE_URL');

    Http::assertNothingSent();
    expect(BrowserTask::sole()->status)->toBe(BrowserTaskStatus::Failed);
});

it('成功時帶上 bearer token 與商品參數', function () {
    Http::fake(['http://browser.test:3031/*' => Http::response([
        'status' => 'succeeded', 'strategy' => 'xhr', 'data' => ['raw' => ['ok' => true]], 'durationMs' => 4321,
    ])]);

    $result = $this->client->scrapeShopeeProduct($this->ref);

    expect($result->succeeded())->toBeTrue()
        ->and($result->strategy)->toBe('xhr')
        ->and($result->durationMs)->toBe(4321);

    Http::assertSent(fn (Request $request) => $request->url() === 'http://browser.test:3031/tasks/shopee-product'
        && $request->hasHeader('Authorization', 'Bearer test-token')
        && $request['shopId'] === 111
        && $request['itemId'] === 222
        && $request['allowDomFallback'] === true);
});

it('5xx 會重試，直到成功為止', function () {
    Http::fake(['http://browser.test:3031/*' => Http::sequence()
        ->push(['errorMessage' => '瀏覽器啟動失敗'], 502)
        ->push(['errorMessage' => '瀏覽器啟動失敗'], 502)
        ->push(['status' => 'succeeded', 'strategy' => 'xhr', 'data' => ['raw' => []]], 200)]);

    $result = $this->client->scrapeShopeeProduct($this->ref);

    expect($result->succeeded())->toBeTrue();
    Http::assertSentCount(3);
    expect(BrowserTask::sole()->attempts)->toBe(3);
});

it('4xx 不重試：我們自己傳錯東西，重試只是浪費時間與配額', function () {
    Http::fake(['http://browser.test:3031/*' => Http::response(['errorMessage' => 'token 不正確'], 401)]);

    $result = $this->client->scrapeShopeeProduct($this->ref);

    expect($result->status)->toBe(BrowserResult::FAILED)
        ->and($result->errorCode)->toBe('http_401');

    Http::assertSentCount(1);
});

it('業務錯誤（HTTP 200 + status=failed）不重試', function () {
    Http::fake(['http://browser.test:3031/*' => Http::response([
        'status' => 'needs_manual', 'errorCode' => 'verification_required', 'errorMessage' => '被導向人機驗證頁',
    ])]);

    $result = $this->client->scrapeShopeeProduct($this->ref);

    expect($result->needsManual())->toBeTrue()
        ->and($result->errorCode)->toBe('verification_required');

    // 重跑幾次都一樣，再送一次只是多一次被風控的機會
    Http::assertSentCount(1);
});

it('重試用盡後仍失敗，嘗試次數與上限都寫進 browser_tasks', function () {
    Http::fake(['http://browser.test:3031/*' => Http::response(['errorMessage' => '掛了'], 500)]);

    $result = $this->client->scrapeShopeeProduct($this->ref);
    $task = BrowserTask::sole();

    expect($result->status)->toBe(BrowserResult::FAILED)
        // 1 次初試 + 3 次重試（delays 有 3 筆）
        ->and($task->attempts)->toBe(4)
        ->and($task->max_attempts)->toBe(4)
        ->and($task->status)->toBe(BrowserTaskStatus::Failed);

    Http::assertSentCount(4);
});

it('連不上服務時視為可重試', function () {
    Http::fake(['http://browser.test:3031/*' => fn () => throw new Illuminate\Http\Client\ConnectionException('cURL error 7')]);

    $result = $this->client->scrapeShopeeProduct($this->ref);

    expect($result->errorCode)->toBe('connection_failed')
        ->and($result->errorMessage)->toContain('cURL error 7')
        // 連線層失敗的請求不會被 Http::fake 記錄，所以用 attempts 驗證有重試
        ->and(BrowserTask::sole()->attempts)->toBe(4);
});

it('降級結果會把 strategy 與警示原因寫進 browser_tasks', function () {
    $product = Product::factory()->shopee(111, 222)->create();

    Http::fake(['http://browser.test:3031/*' => Http::response([
        'status' => 'degraded', 'strategy' => 'dom', 'data' => ['dom' => ['title' => 'DOM 標題']],
        'errorCode' => 'xhr_missed', 'errorMessage' => '沒攔到 get_pc', 'durationMs' => 9000,
    ])]);

    $result = $this->client->scrapeShopeeProduct($this->ref, ['subject' => $product]);
    $task = BrowserTask::sole();

    expect($result->isDegraded())->toBeTrue()
        ->and($task->status)->toBe(BrowserTaskStatus::Degraded)
        ->and($task->strategy_used)->toBe(BrowserStrategy::Dom)
        ->and($task->error_code)->toBe('xhr_missed')
        ->and($task->subject_id)->toBe($product->id)
        ->and($product->refresh()->hasDegradedScrape())->toBeTrue();
});

it('失敗時把截圖與 trace 搬到 S3 並寫進 browser_tasks', function () {
    // browser 容器的 artifacts 目錄是暫存，容器重建就沒了 —— 不搬走等於沒有除錯素材
    Http::fake([
        'http://browser.test:3031/tasks/*' => Http::response([
            'status' => 'failed', 'errorCode' => 'timeout', 'errorMessage' => '等待 get_pc 逾時',
            'artifacts' => ['screenshot' => 'shopee-failed.png', 'trace' => 'shopee-failed.zip'],
        ]),
        'http://browser.test:3031/artifacts/*' => Http::response('binary-bytes', 200),
    ]);

    $this->client->scrapeShopeeProduct($this->ref);
    $task = BrowserTask::sole();

    expect($task->screenshot_path)->toBe("browser/{$task->id}/screenshot.png")
        ->and($task->trace_path)->toBe("browser/{$task->id}/trace.zip")
        ->and($task->har_path)->toBeNull();

    Storage::disk('s3')->assertExists($task->screenshot_path);
    Storage::disk('s3')->assertExists($task->trace_path);
});

it('artifact 下載失敗不會讓整個任務變成別的錯誤', function () {
    Http::fake([
        'http://browser.test:3031/tasks/*' => Http::response([
            'status' => 'failed', 'errorCode' => 'timeout', 'errorMessage' => '逾時',
            'artifacts' => ['screenshot' => 'gone.png'],
        ]),
        'http://browser.test:3031/artifacts/*' => Http::response('', 404),
    ]);

    $result = $this->client->scrapeShopeeProduct($this->ref);

    expect($result->errorCode)->toBe('timeout')
        ->and(BrowserTask::sole()->screenshot_path)->toBeNull();
});

it('外站圖片與 session 檢查打到各自的 endpoint', function () {
    Http::fake(['http://browser.test:3031/*' => Http::response(['status' => 'succeeded', 'strategy' => 'dom', 'data' => []])]);

    $this->client->fetchImagesFromUrl('https://example.test/page', ['min_width' => 800, 'limit' => 5]);
    $this->client->checkSessions(['shopee-seller']);

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/tasks/external-images') && $r['minWidth'] === 800 && $r['limit'] === 5);
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/tasks/shopee-session') && $r['profiles'] === ['shopee-seller']);
});

it('session 檢查只試一次，不重試', function () {
    // 登入狀態檢查本身就是一次真實請求，重試只是加倍風險
    Http::fake(['http://browser.test:3031/*' => Http::response(['errorMessage' => '掛了'], 500)]);

    $this->client->checkSessions();

    Http::assertSentCount(1);
});
