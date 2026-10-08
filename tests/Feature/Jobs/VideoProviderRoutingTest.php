<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Jobs\GenerateAssetsJob;
use App\Jobs\GenerateShotVideoJob;
use App\Models\Product;
use App\Services\Compliance\AdComplianceChecker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 * P6 動畫 provider 路由的回歸測試。
 *
 * 這組測試存在的原因是一個真實的靜默失效：GenerateAssetsJob 原本寫死
 *   $withVideo = (string) $product->video_provider === VideoProvider::Kling->value;
 * 所以 video_provider = dola 時判斷為 false，整段動畫派工被跳過 ——
 * 鏡頭標成 skipped、商品一路自動放行到渲染，operator 以為設定生效了，
 * 但影片永遠沒有 AI 動畫，而且沒有任何錯誤訊息。
 *
 * 現在的規則：none → skipped（正常往下走）；provider 不可用 → 明確 failed + 寫原因。
 *
 * ⚠️ 全程不會打真實 API：Tests\TestCase::setUp() 強制 services.use_real_apis = false
 *    並把 VideoGeneratorContract 綁成 StubVideoGenerator，VideoGeneratorFactory 在
 *    stub 模式下也只會回那顆 stub（見 VideoGeneratorFactory::make 的註解）。
 */

beforeEach(function () {
    // 動畫可用性是看真實設定（刻意不受 use_real_apis 影響），所以測試要自己把狀態釘死
    config([
        'services.kling.access_key' => 'test-ak',
        'services.kling.secret_key' => 'test-sk-must-be-at-least-32-bytes-long',
        'services.browser.profiles.dola' => '',
        'services.video_provider' => 'none',
    ]);
});

/** 腳本已核准、可以直接進素材階段的商品（鏡頭的 video_provider 預設 null = 繼承） */
function routableProduct(array $attributes = [], int $shots = 3): Product
{
    return Product::factory()->renderable($shots)->create([
        'status' => ProductStatus::ScriptApproved,
        'compliance_passed' => true,
        'compliance_rules_fingerprint' => app(AdComplianceChecker::class)->rulesFingerprint(),
        ...$attributes,
    ]);
}

it('provider = none：鏡頭標 skipped（不是 failed），流程正常往下走', function () {
    $product = routableProduct(['video_provider' => 'none']);

    dispatch(new GenerateAssetsJob($product->id));

    $product->refresh();
    expect($product->shots()->where('video_status', 'skipped')->count())->toBe(3)
        ->and($product->shots()->where('video_status', 'failed')->count())->toBe(0)
        ->and($product->status)->toBe(ProductStatus::FinalPendingReview)
        ->and($product->status_message)->toBeNull();
});

it('provider = dola（尚未實作）：明確失敗，鏡頭與商品都看得到可讀的原因', function () {
    $product = routableProduct(['video_provider' => 'dola']);

    dispatch(new GenerateAssetsJob($product->id));

    $product->refresh();
    $shot = $product->shots()->first();

    expect($product->shots()->where('video_status', 'failed')->count())->toBe(3)
        ->and($product->shots()->where('video_status', 'skipped')->count())->toBe(0)
        ->and($shot->video_error)->toContain('NOT_IMPLEMENTED')
        ->and($shot->video_error)->toContain('Dola')
        ->and($product->status_message)->toContain('NOT_IMPLEMENTED')
        // 不是靜默放行：停在部分失敗等人處理，絕不自動渲染
        ->and($product->status)->toBe(ProductStatus::AssetsPartial);
});

it('provider = kling 但缺金鑰：同樣明確失敗，不是靜默跳過', function () {
    config(['services.kling.secret_key' => null]);
    $product = routableProduct(['video_provider' => 'kling']);

    dispatch(new GenerateAssetsJob($product->id));

    $product->refresh();
    expect($product->shots()->where('video_status', 'failed')->count())->toBe(3)
        ->and($product->shots()->first()->video_error)->toContain('KLING_SECRET_KEY')
        ->and($product->status_message)->toContain('KLING_SECRET_KEY')
        ->and($product->status)->toBe(ProductStatus::AssetsPartial);
});

it('operator 把供應商改回 none 後，上一輪的 failed 會被清成 skipped 而不是永遠卡住', function () {
    $product = routableProduct(['video_provider' => 'dola']);
    dispatch(new GenerateAssetsJob($product->id));
    expect($product->refresh()->status)->toBe(ProductStatus::AssetsPartial);

    $product->update(['video_provider' => 'none']);
    dispatch(new GenerateAssetsJob($product->id));

    $product->refresh();
    expect($product->shots()->where('video_status', 'skipped')->count())->toBe(3)
        ->and($product->shots()->whereNotNull('video_error')->count())->toBe(0)
        ->and($product->status)->toBe(ProductStatus::FinalPendingReview);
});

it('混合模式：兩鏡 none、一鏡 kling，只有 kling 那鏡會派工', function () {
    // 只 fake 動畫任務 —— 整個 Queue::fake() 會連 GenerateAssetsJob 自己都攔下來不執行
    Queue::fake([GenerateShotVideoJob::class]);
    $product = routableProduct(['video_provider' => 'none']);
    $kling = $product->shots()->orderBy('shot_order')->first();
    $kling->update(['video_provider' => 'kling']);

    dispatch(new GenerateAssetsJob($product->id));

    Queue::assertPushed(GenerateShotVideoJob::class, 1);
    Queue::assertPushed(GenerateShotVideoJob::class, fn (GenerateShotVideoJob $job) => $job->shotId === $kling->id);

    expect($kling->refresh()->video_status)->toBe('pending')
        ->and($product->shots()->where('video_status', 'skipped')->count())->toBe(2);
});

it('派工不會把解析結果回寫進 shot.video_provider（那一欄是 operator 的覆寫）', function () {
    Storage::fake('public');
    Http::fake(['placehold.co/*' => Http::response('fake-mp4', 200)]);
    $product = routableProduct(['video_provider' => 'kling']);

    dispatch(new GenerateAssetsJob($product->id));

    expect($product->shots()->whereNull('video_provider')->count())->toBe(3)
        ->and($product->shots()->where('video_status', 'done')->count())->toBe(3);
});

// --- autopilot ③ ---

it('只要有任一鏡頭不是 none，autopilot ③ 就不放行', function () {
    Storage::fake('public');
    Http::fake(['placehold.co/*' => Http::response('fake-mp4', 200)]);

    expect(config('video.autopilot.assets'))->toBeTrue();

    $product = routableProduct(['video_provider' => 'none']);
    $product->shots()->orderBy('shot_order')->first()->update(['video_provider' => 'kling']);

    dispatch(new GenerateAssetsJob($product->id));

    // 有 AI 動畫 → 停在 ③ 等人看有沒有把商品畫變形
    expect($product->refresh()->status)->toBe(ProductStatus::AssetsPendingReview);
});

it('對照組：全部鏡頭都是 none 時 autopilot ③ 會放行（證明 gate 不是壞掉）', function () {
    $product = routableProduct(['video_provider' => 'none']);

    dispatch(new GenerateAssetsJob($product->id));

    expect($product->refresh()->status)->toBe(ProductStatus::FinalPendingReview)
        ->and($product->statusHistory()->where('to_status', 'assets_approved')->value('triggered_by'))->toBe('autopilot');
});

it('商品留空、config 全域設成 kling 時，鏡頭仍然會做動畫（config 是第三層）', function () {
    Queue::fake([GenerateShotVideoJob::class]);
    config(['services.video_provider' => 'kling']);
    $product = routableProduct(['video_provider' => null]);

    dispatch(new GenerateAssetsJob($product->id));

    Queue::assertPushed(GenerateShotVideoJob::class, 3);
    expect($product->shots()->where('video_status', 'pending')->count())->toBe(3);
});

it('GenerateShotVideoJob 用逐鏡解析的 provider 送出（stub 模式下拿到 stub 的 task_id）', function () {
    $product = routableProduct(['video_provider' => 'kling'], 1);
    $shot = $product->shots()->first();
    $shot->update(['video_status' => 'pending']);

    dispatch(new GenerateShotVideoJob($shot->id));

    expect($shot->refresh()->video_request_id)->toStartWith('stub_video_');
});

it('⚠️ 全域預設 none 但商品覆寫 kling 時，輪詢用的是該鏡頭的 provider', function () {
    // 這是預設設定下就會踩到的 bug 的回歸測試：
    // PollKlingVideoJob 若拿全域綁定會解析到 NullVideoGenerator，
    // queryTaskStatus() 丟例外被 catch 吞掉重排，空轉 30 次才報「Polling timeout」。
    config([
        'services.use_real_apis' => true,
        'services.video_provider' => 'none',          // 全域預設
        'services.kling.access_key' => 'ak-test',
        'services.kling.secret_key' => 'sk-test',
    ]);

    $product = Product::factory()->renderable(1)->create(['video_provider' => 'kling']);
    $shot = $product->shots()->first();

    $resolved = app(\App\Services\Video\VideoGeneratorFactory::class)->resolveFor($shot);

    expect($resolved)->toBeInstanceOf(\App\Services\KlingVideoGenerator::class)
        ->and($resolved->name())->toBe(\App\Enums\VideoProvider::Kling);
});
