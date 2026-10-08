<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\Compliance\AdComplianceChecker;
use App\Services\Pipeline;

/**
 * autopilot 的「對照組」。
 *
 * AutopilotGateTest 有 8 條測試，其中 7 條驗的是「不該放行時狀態維持 ScriptPendingReview」。
 * 唯一的正向案例斷言是 `->not->toBe(ProductStatus::ScriptPendingReview)` —— 太弱：
 * 只要 afterScriptGenerated() 把狀態改成任何別的值（NeedsManual、Archived，甚至轉錯）
 * 它一樣綠。也就是說，如果自動放行整個壞掉成「一律轉去某個奇怪狀態」，
 * 8 條裡會有 7 條綠 + 1 條也綠。
 *
 * 這個檔補上精確的正向斷言：放行時必須恰好寫出 script_approved，triggered_by = autopilot，
 * 並且真的把後續 pipeline 接下去。
 *
 * ⚠️ AutopilotGateTest.php 本身刻意不動（它是防護網檔案）。
 */
beforeEach(function () {
    // ⚠️ 這個檔是唯一刻意把 autopilot.script 打開的地方。預設 false 由 AutopilotGateTest 守。
    config(['video.autopilot.script' => true]);
});

function autopilotReadyProduct(array $attributes = []): Product
{
    return Product::factory()->renderable(3)->create([
        'status' => ProductStatus::ScriptPendingReview,
        'compliance_passed' => true,
        'compliance_report' => ['findings' => []],
        'compliance_rules_version' => app(AdComplianceChecker::class)->rulesVersion(),
        'compliance_rules_fingerprint' => app(AdComplianceChecker::class)->rulesFingerprint(),
        'caption' => '這是一段乾淨的測試文案',
        'hashtags' => ['耳機', '開箱', '好物'],
        ...$attributes,
    ]);
}

it('writes exactly one autopilot script_approved row when it releases', function () {
    // assets 的自動放行另外測，這裡只要盯 ② 這一步
    config(['video.autopilot.assets' => false]);

    $product = autopilotReadyProduct();
    app(Pipeline::class)->afterScriptGenerated($product);

    $row = $product->statusHistory()->where('to_status', 'script_approved')->sole();

    expect($row->from_status)->toBe('script_pending_review')
        ->and($row->triggered_by)->toBe('autopilot')
        // 放行後必須真的把素材階段接下去（否則商品會卡在 script_approved 沒人推）
        ->and($product->refresh()->status)->toBe(ProductStatus::AssetsPendingReview)
        ->and($product->statusHistory()->orderBy('id')->pluck('to_status')->all())
        ->toBe(['script_approved', 'assets_generating', 'assets_pending_review']);
});

it('carries a clean script all the way to the final checkpoint', function () {
    // autopilot 全開時唯一該停下來的地方是 ④ 成品待審核 —— 上架前的人工把關不可自動化
    $product = autopilotReadyProduct();

    app(Pipeline::class)->afterScriptGenerated($product);

    expect($product->refresh()->status)->toBe(ProductStatus::FinalPendingReview)
        ->and($product->final_video_remote_url)->toBe('https://placehold.co/1080x1920.mp4')
        ->and($product->statusHistory()->where('to_status', 'script_approved')->value('triggered_by'))->toBe('autopilot')
        ->and($product->statusHistory()->where('to_status', 'assets_approved')->value('triggered_by'))->toBe('autopilot')
        // ④ 之後沒有任何自動放行，必須停在這裡
        ->and($product->statusHistory()->whereIn('to_status', ['ready_to_publish', 'publishing', 'published', 'completed'])->count())->toBe(0);
});

it('leaves the product untouched when it declines to release', function () {
    // 對照組的對照組：不放行時「完全沒有」寫 history，而不是寫了一筆又改回來
    $product = autopilotReadyProduct(['disclosure_prefix' => '']);

    app(Pipeline::class)->afterScriptGenerated($product);

    expect($product->refresh()->status)->toBe(ProductStatus::ScriptPendingReview)
        ->and($product->statusHistory()->count())->toBe(0);
});
