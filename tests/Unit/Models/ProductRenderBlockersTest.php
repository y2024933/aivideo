<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\Shot;
use App\Services\Compliance\AdComplianceChecker;

/** 可直接送渲染的商品：沒有任何 blocker */
function renderReadyProduct(array $attributes = []): Product
{
    return Product::factory()->renderable(3)->create([
        'compliance_passed' => true,
        'compliance_rules_fingerprint' => app(AdComplianceChecker::class)->rulesFingerprint(),
        ...$attributes,
    ])->fresh();
}

it('has no blockers when everything is ready', function () {
    expect(renderReadyProduct()->renderBlockers())->toBe([]);
});

it('blocks when fewer than two shots have a remote url', function () {
    $product = renderReadyProduct();
    $product->shots->skip(1)->each(fn (Shot $s) => $s->update(['image_remote_url' => null]));

    expect($product->fresh()->renderBlockers())
        ->toContain('可渲染鏡頭不足 2 個（需有 image_remote_url 或 video_remote_url）');
});

it('blocks on simplified chinese in a shot', function () {
    $product = renderReadyProduct();
    $product->shots->first()->update(['subtitle_has_simplified' => true]);

    expect($product->fresh()->renderBlockers())->toContain('有鏡頭字幕含簡體字');
});

it('blocks on glyph variants that the Noto TC subset font may not cover', function () {
    $product = renderReadyProduct();
    $product->shots->first()->update(['compliance_flags' => [
        ['category' => 'glyph_variant', 'matched' => '裏', 'severity' => 'warning'],
    ]]);

    expect($product->fresh()->renderBlockers())
        ->toContain('有鏡頭字幕含大陸字形（Noto TC 字型可能缺字，會渲染成方塊 □）');
});

it('blocks when compliance has not passed', function () {
    expect(renderReadyProduct(['compliance_passed' => false])->renderBlockers())->toContain('合規檢查未通過');
});

it('blocks when the product was scanned with an outdated rule set', function () {
    expect(renderReadyProduct(['compliance_rules_fingerprint' => 'stale0000000'])->renderBlockers())
        ->toContain('合規規則已更新，請重新檢查');
});

it('blocks without a disclosure prefix', function () {
    expect(renderReadyProduct(['disclosure_prefix' => null])->renderBlockers())
        ->toContain('未設定聯盟行銷揭露前綴');
});

it('blocks when tts mode has an unfinished voiceover', function () {
    $product = renderReadyProduct(['audio_mode' => 'tts']);
    $product->shots->first()->update(['voiceover_text' => '降噪開啟後，世界瞬間安靜。', 'voiceover_status' => 'pending']);

    expect($product->fresh()->renderBlockers())->toContain('配音模式但有鏡頭配音未完成');

    // 同樣的鏡頭在非 tts 模式下不該被擋
    $product->update(['audio_mode' => 'none']);
    expect($product->fresh()->renderBlockers())->toBe([]);
});

it('blocks while another render is already running', function () {
    expect(renderReadyProduct(['render_id' => 'render_abc'])->renderBlockers())->toContain('已有渲染任務進行中');
});
