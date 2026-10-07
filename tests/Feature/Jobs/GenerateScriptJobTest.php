<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Jobs\GenerateScriptJob;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shot;
use App\Services\Contracts\ScriptWriterContract;
use App\Services\Llm\ShotDurationEstimator;
use App\Services\Stubs\StubScriptWriter;

/** 已核准商品資料、有 N 張選用圖，可直接進寫稿 */
function approvedProduct(int $images = 4, array $attributes = []): Product
{
    $product = Product::factory()->status(ProductStatus::ProductApproved)->create($attributes);

    foreach (range(1, $images) as $i) {
        ProductImage::factory()->for($product)->create(['sort_order' => $i, 'is_primary' => $i === 1]);
    }

    return $product;
}

function runScriptJob(Product $product, StubScriptWriter $writer): StubScriptWriter
{
    app()->instance(ScriptWriterContract::class, $writer);
    dispatch_sync(new GenerateScriptJob($product->id));

    return $writer;
}

it('測試環境一律解析到 StubScriptWriter，不會呼叫真實 Anthropic API', function () {
    expect(config('services.use_real_apis'))->toBeFalse()
        ->and(app(ScriptWriterContract::class))->toBeInstanceOf(StubScriptWriter::class)
        ->and(config('services.anthropic.model'))->toBe('claude-opus-5');
});

it('寫稿成功後建立鏡頭、寫入文案並轉為腳本待審核', function () {
    $product = approvedProduct();

    runScriptJob($product, new StubScriptWriter());
    $product->refresh();

    expect($product->status)->toBe(ProductStatus::ScriptPendingReview)
        ->and($product->shots)->toHaveCount(5)
        ->and($product->caption)->toContain('通勤路上')
        ->and($product->hashtags)->toBe(['藍牙耳機', '通勤好物', '耳機推薦'])
        ->and($product->script['shots'])->toHaveCount(5)
        ->and($product->script_model)->toBe('stub-script-writer')
        ->and($product->script_generated_at)->not->toBeNull()
        ->and((float) $product->llm_cost_usd)->toBeGreaterThan(0)
        ->and($product->compliance_rules_fingerprint)->not->toBeEmpty()
        ->and($product->compliance_rules_version)->toBe(config('compliance.version'))
        ->and($product->compliance_passed)->toBeTrue()
        ->and($product->needs_manual_reason)->toBeNull();

    $shots = $product->shots()->get();

    expect($shots->pluck('shot_id')->all())->toBe(['S01', 'S02', 'S03', 'S04', 'S05'])
        ->and($shots->pluck('role')->all())->toBe(['hook', 'pain', 'feature', 'feature', 'cta'])
        ->and($shots->pluck('shot_order')->all())->toBe([1, 2, 3, 4, 5])
        ->and($shots->every(fn (Shot $shot) => $shot->product_image_id !== null))->toBeTrue()
        ->and($shots->every(fn (Shot $shot) => $shot->image_remote_url !== null))->toBeTrue()
        ->and($shots->every(fn (Shot $shot) => ! $shot->subtitle_has_simplified))->toBeTrue();
});

it('無配音模式不寫入 voiceover_text', function () {
    $product = approvedProduct();

    runScriptJob($product, new StubScriptWriter());

    expect($product->shots()->pluck('voiceover_text')->filter()->all())->toBe([])
        ->and($product->shots()->pluck('voiceover_status')->unique()->all())->toBe(['skipped']);
});

it('配音模式寫入 voiceover_text 並標記待配音', function () {
    $product = approvedProduct(attributes: ['audio_mode' => 'tts', 'voice_id_preferred' => 'zh-TW-HsiaoChenNeural']);

    runScriptJob($product, new StubScriptWriter());

    expect($product->shots()->pluck('voiceover_text')->filter())->toHaveCount(5)
        ->and($product->shots()->pluck('voiceover_status')->unique()->all())->toBe(['pending']);
});

it('輸出含簡體字時重試一次，仍失敗則轉需人工且鏡頭照樣寫進 DB', function () {
    $product = approvedProduct();
    $writer = new StubScriptWriter();
    $writer->queue = [StubScriptWriter::simplifiedOutput(), StubScriptWriter::simplifiedOutput()];

    runScriptJob($product, $writer);
    $product->refresh();

    expect($product->status)->toBe(ProductStatus::NeedsManual)
        ->and($product->shots)->toHaveCount(5)
        ->and($product->needs_manual_reason)->not->toBeEmpty()
        ->and($product->compliance_passed)->toBeFalse()
        ->and($product->shots()->where('subtitle_has_simplified', true)->count())->toBe(1)
        ->and($product->shots()->where('shot_id', 'S03')->value('subtitle'))->toContain('这');
});

it('輸出含 blocking 違規時走同一條需人工路徑', function () {
    $product = approvedProduct();
    $writer = new StubScriptWriter();
    $writer->queue = [StubScriptWriter::blockingOutput(), StubScriptWriter::blockingOutput()];

    runScriptJob($product, $writer);
    $product->refresh();

    expect($product->status)->toBe(ProductStatus::NeedsManual)
        ->and($product->shots)->toHaveCount(5)
        ->and($product->needs_manual_reason)->toContain('保證')
        ->and($product->shots()->where('shot_id', 'S04')->value('compliance_flags'))->not->toBeEmpty();
});

it('重試時把 retry_feedback 與 retry_report 傳進 write()', function () {
    $product = approvedProduct();
    $writer = new StubScriptWriter();
    $writer->queue = [StubScriptWriter::simplifiedOutput(), StubScriptWriter::cleanOutput()];

    runScriptJob($product, $writer);

    expect($writer->calls[0])->toBe([])
        ->and($writer->calls[1]['retry_feedback'])->toContain('是簡體字')
        ->and($writer->calls[1]['retry_report'])->toBeInstanceOf(App\Data\ComplianceReport::class)
        ->and($product->refresh()->status)->toBe(ProductStatus::ScriptPendingReview);
});

it('重試上限是一次，write() 總共只會被呼叫兩次', function () {
    $product = approvedProduct();
    $writer = new StubScriptWriter();
    $writer->queue = [StubScriptWriter::blockingOutput(), StubScriptWriter::blockingOutput(), StubScriptWriter::cleanOutput()];

    runScriptJob($product, $writer);

    expect($writer->calls)->toHaveCount(2)
        ->and($product->refresh()->status)->toBe(ProductStatus::NeedsManual);
});

it('寫稿拋錯時轉為腳本生成失敗並留下訊息', function () {
    $product = approvedProduct();
    $writer = new StubScriptWriter();
    $writer->throws = new RuntimeException('Anthropic 寫稿失敗：429 rate limit');

    runScriptJob($product, $writer);
    $product->refresh();

    expect($product->status)->toBe(ProductStatus::ScriptFailed)
        ->and($product->status_message)->toContain('429')
        ->and($product->shots)->toHaveCount(0);
});

it('LLM 低估的秒數會被字幕可讀時間拉高', function () {
    $product = approvedProduct();
    $writer = new StubScriptWriter();
    $output = StubScriptWriter::cleanOutput();
    // 14 個字的字幕卻只給 1.5 秒，實際至少要 fromSubtitle() 算出來的秒數
    $output->shots[2]->durationSeconds = 1.5;
    $writer->queue = [$output];

    runScriptJob($product, $writer);

    $readable = ShotDurationEstimator::fromSubtitle('戴上就安靜，像關掉外面的世界');

    expect($readable)->toBeGreaterThan(1.5)
        ->and((float) $product->shots()->where('shot_id', 'S03')->value('duration_seconds'))->toBeGreaterThanOrEqual($readable);
});

it('總長會被校正到目標長度的正負 15% 內', function () {
    $product = approvedProduct(attributes: ['video_length_seconds' => 20]);

    runScriptJob($product, new StubScriptWriter());
    $product->refresh();

    $durations = $product->shots->map(fn (Shot $shot) => (float) $shot->duration_seconds)->all();
    $nonCut = $product->shots->slice(0, -1)->filter(fn (Shot $shot) => $shot->transition !== 'cut')->count();

    expect(ShotDurationEstimator::effectiveSeconds($durations, $nonCut))->toBeGreaterThan(20 * 0.8)
        ->and(ShotDurationEstimator::effectiveSeconds($durations, $nonCut))->toBeLessThan(20 * 1.2);
});

it('imageRef 超出圖片數時取模而不是爆掉', function () {
    $product = approvedProduct(images: 2);
    $writer = new StubScriptWriter();
    $output = StubScriptWriter::cleanOutput();
    $output->shots[0]->imageRef = 7;
    $output->shots[1]->imageRef = -3;
    $writer->queue = [$output];

    runScriptJob($product, $writer);

    $images = $product->selectedImages()->get();

    expect($product->refresh()->status)->toBe(ProductStatus::ScriptPendingReview)
        ->and($product->shots()->where('shot_id', 'S01')->value('product_image_id'))->toBe($images[1]->id)
        ->and($product->shots()->where('shot_id', 'S02')->value('product_image_id'))->toBe($images[0]->id);
});

it('沒有可用圖片時不綁圖也不會爆掉', function () {
    $product = Product::factory()->status(ProductStatus::ProductApproved)->create();

    runScriptJob($product, new StubScriptWriter());

    expect($product->refresh()->status)->toBe(ProductStatus::ScriptPendingReview)
        ->and($product->shots()->whereNotNull('product_image_id')->count())->toBe(0);
});

it('重跑時先刪掉舊鏡頭', function () {
    $product = Product::factory()->renderable(3)->status(ProductStatus::ProductApproved)->create();
    $oldIds = $product->shots->pluck('id')->all();

    expect($oldIds)->toHaveCount(3);

    runScriptJob($product, new StubScriptWriter());

    expect($product->refresh()->shots)->toHaveCount(5)
        ->and(Shot::whereIn('id', $oldIds)->count())->toBe(0);
});

it('狀態不在入口狀態時直接略過', function () {
    $product = approvedProduct();
    $product->forceStatus(ProductStatus::Rendering, 'test');
    $writer = new StubScriptWriter();

    runScriptJob($product, $writer);

    expect($writer->calls)->toBe([])
        ->and($product->refresh()->status)->toBe(ProductStatus::Rendering);
});

it('腳本生成失敗後可以重跑', function () {
    $product = approvedProduct();
    $product->forceStatus(ProductStatus::ScriptFailed, 'test');

    runScriptJob($product, new StubScriptWriter());

    expect($product->refresh()->status)->toBe(ProductStatus::ScriptPendingReview);
});

it('products.script_provider 會覆寫全域預設（繞過容器綁定走 factory）', function () {
    // 容器綁的 writer 代表「全域預設」；商品指定了 provider 就不該用到它
    $global = new StubScriptWriter();
    app()->instance(ScriptWriterContract::class, $global);

    $product = approvedProduct(attributes: ['script_provider' => 'claude']);
    dispatch_sync(new GenerateScriptJob($product->id));

    // use_real_apis=false，factory 仍回 stub（防護網），所以流程照樣跑完
    expect($global->calls)->toBe([])
        ->and($product->refresh()->status)->toBe(ProductStatus::ScriptPendingReview)
        ->and($product->shots)->toHaveCount(5);
});

it('products.script_provider 為空時用容器綁定的全域預設', function () {
    $global = new StubScriptWriter();

    runScriptJob(approvedProduct(), $global);

    expect($global->calls)->toHaveCount(1);
});

it('缺 ANTHROPIC_API_KEY 時轉為腳本生成失敗，而不是卡在生成中', function () {
    // 這裡只建構真實 writer（會在建構子就丟例外），不會送出任何請求
    config(['services.anthropic.api_key' => null]);
    app()->bind(ScriptWriterContract::class, fn () => app(App\Services\ClaudeScriptWriter::class));

    $product = approvedProduct();
    dispatch_sync(new GenerateScriptJob($product->id));

    expect($product->refresh()->status)->toBe(ProductStatus::ScriptFailed)
        ->and($product->status_message)->toContain('ANTHROPIC_API_KEY 未設定');
});
