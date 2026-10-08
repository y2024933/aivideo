<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\Compliance\AdComplianceChecker;
use App\Services\Pipeline;

/*
 * 自動放行的安全閘門。
 *
 * autopilot.script 是整個系統最危險的一個開關 —— 它是唯一可能讓「不合規的稿」
 * 不經人眼直接衝到渲染與上架的路徑。而違規的法律責任在 operator 身上
 * （公平會明文把網紅／部落客列為廣告主），所以這裡的每一條都要有測試守著。
 *
 * 設計上的三道鎖：
 *   1. autopilot.script 預設 false
 *   2. 只有「零 finding」才放行 —— 不是「零 blocking」。LLM 剛寫出來的稿
 *      不可能有人 acknowledge 過，有 warning 就代表沒人看過。
 *   3. scriptApprovalBlockers() 必須為空（簡體字、缺揭露前綴等）
 */

function cleanPendingScript(array $attributes = []): Product
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

function autopilotFinding(string $severity, string $matched = '保證有效'): array
{
    return [
        'field' => 'S01.subtitle', 'offset' => 0, 'length' => mb_strlen($matched), 'matched' => $matched,
        'ruleId' => 'guarantee', 'severity' => $severity, 'category' => 'guarantee',
        'law' => '公平交易法 §21', 'message' => '測試用', 'suggestion' => null,
        'suggestions' => [], 'confidence' => 'high', 'acknowledged' => false,
    ];
}

it('預設不自動放行 checkpoint ②：乾淨的稿也要停下等人', function () {
    expect(config('video.autopilot.script'))->toBeFalse();

    $product = cleanPendingScript();
    app(Pipeline::class)->afterScriptGenerated($product);

    expect($product->refresh()->status)->toBe(ProductStatus::ScriptPendingReview);
});

it('開啟 autopilot.script 後，零 finding 的稿才會自動放行', function () {
    config(['video.autopilot.script' => true]);

    $product = cleanPendingScript();
    app(Pipeline::class)->afterScriptGenerated($product);

    // ⚠️ 不可以只斷言「狀態變了」。全檔其他 7 條都在驗「不該放行時維持
    //    ScriptPendingReview」，這條是唯一的正向對照組 —— 若 afterScriptGenerated()
    //    壞成「一律轉去某個錯誤狀態」（NeedsManual、Archived、轉錯 checkpoint），
    //    弱斷言會讓 8 條全部照樣綠，等於整個 gate 失效而沒人知道。
    // 精確驗「② 被 autopilot 放行」這一跳本身，而不是只看最終狀態 ——
    // autopilot 鏈會繼續往下跑（③ 也放行、渲染完成），終點是 ④ 成品待審核。
    $hop = $product->statusHistory()->where('to_status', 'script_approved')->first();
    expect($hop)->not->toBeNull()
        ->and($hop->from_status)->toBe('script_pending_review')
        ->and($hop->triggered_by)->toBe('autopilot');

    // 並且確認鏈條停在 ④ —— 上架前的人工把關不會被 autopilot 跨過
    expect($product->refresh()->status)->toBe(ProductStatus::FinalPendingReview)
        ->and($product->statusHistory()->pluck('to_status')->all())
        ->not->toContain('ready_to_publish')
        ->not->toContain('published');
});

it('有 blocking 違規時，即使 autopilot 開著也絕不放行', function () {
    config(['video.autopilot.script' => true]);

    $product = cleanPendingScript([
        'compliance_report' => ['findings' => [autopilotFinding('blocking')]],
        'compliance_passed' => false,
    ]);

    app(Pipeline::class)->afterScriptGenerated($product);

    expect($product->refresh()->status)->toBe(ProductStatus::ScriptPendingReview);
});

it('只有 warning 也不放行：LLM 剛產的稿不可能有人確認過', function () {
    config(['video.autopilot.script' => true]);

    $product = cleanPendingScript([
        'compliance_report' => ['findings' => [autopilotFinding('warning', '最推薦')]],
    ]);

    app(Pipeline::class)->afterScriptGenerated($product);

    expect($product->refresh()->status)->toBe(ProductStatus::ScriptPendingReview);
});

it('warning 被標成已確認也不放行：acknowledged 只有人按得出來', function () {
    config(['video.autopilot.script' => true]);

    $acknowledged = autopilotFinding('warning', '最推薦');
    $acknowledged['acknowledged'] = true;

    $product = cleanPendingScript(['compliance_report' => ['findings' => [$acknowledged]]]);
    app(Pipeline::class)->afterScriptGenerated($product);

    expect($product->refresh()->status)->toBe(ProductStatus::ScriptPendingReview);
});

it('缺聯盟行銷揭露前綴時不放行', function () {
    config(['video.autopilot.script' => true]);

    $product = cleanPendingScript(['disclosure_prefix' => '']);
    app(Pipeline::class)->afterScriptGenerated($product);

    expect($product->refresh()->status)->toBe(ProductStatus::ScriptPendingReview);
});

it('狀態不是 ScriptPendingReview 時不會被放行', function () {
    config(['video.autopilot.script' => true]);

    $product = cleanPendingScript(['status' => ProductStatus::NeedsManual]);
    app(Pipeline::class)->afterScriptGenerated($product);

    expect($product->refresh()->status)->toBe(ProductStatus::NeedsManual);
});

it('Pipeline 不含任何把 ④ 成品、待上架或完成自動放行的路徑', function () {
    // 上架前的人工把關是最後一道防線，程式裡不該存在繞過它的分支。
    $source = file_get_contents(app_path('Services/Pipeline.php'));

    expect($source)
        ->not->toContain('ReadyToPublish')
        ->not->toContain('Published')
        ->not->toContain('Completed');
});
