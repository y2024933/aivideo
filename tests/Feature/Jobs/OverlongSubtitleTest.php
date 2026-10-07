<?php

declare(strict_types=1);

use App\Data\Llm\ScriptOutput;
use App\Data\Llm\ScriptShot;
use App\Enums\ProductStatus;
use App\Jobs\GenerateScriptJob;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Contracts\ScriptWriterContract;
use App\Services\Stubs\StubScriptWriter;

/*
 * 字幕字數上限的把關。
 *
 * SDK 的 #[Constrained(maxLength: 22)] 對中文無效 —— StructuredOutput.php:259 用的是
 * strlen()（位元組），22 個中文字 = 66 bytes 必定「違規」，而違規只寫進
 * validation_warnings 不丟例外。所以字數必須由 GenerateScriptJob 自己用 mb_strlen 檢查。
 */

function overlongOutput(int $chars): ScriptOutput
{
    $long = str_repeat('降噪效果真的很有感覺啊', 10);

    return new ScriptOutput(
        shots: [
            new ScriptShot(role: 'hook', imageRef: 0, kenBurns: 'zoomIn', durationSeconds: 3.0,
                subtitle: mb_substr($long, 0, $chars), voiceoverText: '', transition: 'crossfade'),
            new ScriptShot(role: 'cta', imageRef: 0, kenBurns: 'zoomOut', durationSeconds: 3.0,
                subtitle: '連結放在留言區', voiceoverText: '', transition: 'cut'),
        ],
        caption: '這是一段測試用的貼文文案',
        hashtags: ['耳機', '開箱', '好物'],
    );
}

function productReadyForScript(): Product
{
    $product = Product::factory()->status(ProductStatus::ProductApproved)->create();
    ProductImage::factory()->count(2)->for($product)->create();

    return $product;
}

it('字幕超過 22 字會觸發重寫', function () {
    $stub = new StubScriptWriter();
    $stub->queue = [overlongOutput(40), overlongOutput(18)];
    app()->instance(ScriptWriterContract::class, $stub);

    GenerateScriptJob::dispatchSync(productReadyForScript()->id);

    expect($stub->calls)->toHaveCount(2)
        ->and($stub->calls[1]['retry_feedback'] ?? '')->toContain('超過上限 22 字');
});

it('重寫後仍超長就轉人工，並在原因裡說明會折行', function () {
    $stub = new StubScriptWriter();
    $stub->queue = [overlongOutput(40), overlongOutput(38)];
    app()->instance(ScriptWriterContract::class, $stub);

    $product = productReadyForScript();
    GenerateScriptJob::dispatchSync($product->id);

    $product->refresh();

    expect($product->status)->toBe(ProductStatus::NeedsManual)
        ->and($product->needs_manual_reason)->toContain('折行蓋住畫面')
        ->and($product->shots()->count())->toBe(2);   // 仍寫進 DB 讓 operator 看得到
});

it('剛好 22 字不算超長', function () {
    $stub = new StubScriptWriter();
    $stub->queue = [overlongOutput(22)];
    app()->instance(ScriptWriterContract::class, $stub);

    $product = productReadyForScript();
    GenerateScriptJob::dispatchSync($product->id);

    expect($stub->calls)->toHaveCount(1)
        ->and($product->refresh()->status)->toBe(ProductStatus::ScriptPendingReview);
});
