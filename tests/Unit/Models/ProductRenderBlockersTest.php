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

// ─── 以下為 P10 補的對照組：原本每條 blocker 只驗「會擋」，沒驗「不該擋時不擋」 ───

it('counts a b-roll shot as renderable even without a product image', function () {
    // renderableUrl() 的另一半分支：video_status = done 時用 video_remote_url。
    // 原本的測試只蓋到 image_remote_url，B-roll 這條路整個壞掉也不會紅。
    $product = renderReadyProduct();
    $product->shots->each(fn (Shot $s) => $s->update([
        'image_remote_url' => null,
        'video_status' => 'done',
        'video_remote_url' => 'https://s3.test/videos/'.$s->shot_id.'.mp4',
    ]));

    expect($product->fresh()->renderBlockers())->toBe([]);
});

it('does not count a done b-roll shot that never got an s3 url', function () {
    // video_status = done 但 remote_url 是 null → Lambda 讀不到，不算可渲染
    $product = renderReadyProduct();
    $product->shots->each(fn (Shot $s) => $s->update([
        'image_remote_url' => null,
        'video_status' => 'done',
        'video_remote_url' => null,
    ]));

    expect($product->fresh()->renderBlockers())
        ->toContain('可渲染鏡頭不足 2 個（需有 image_remote_url 或 video_remote_url）');
});

it('ignores compliance flags that are not glyph variants', function () {
    // 對照組：字形以外的 flag（例如 warning 等級的誇大用語）不該擋渲染，
    // 否則「有 compliance_flags 就擋」會讓所有有 warning 的商品都渲染不出來。
    $product = renderReadyProduct();
    $product->shots->first()->update(['compliance_flags' => [
        ['category' => 'spec_overclaim', 'matched' => '續航', 'severity' => 'warning'],
    ]]);

    expect($product->fresh()->renderBlockers())->toBe([]);
});

it('reports every blocker at once instead of stopping at the first', function () {
    // operator 要一次看到全部缺項；只回第一個會變成「修一個再被擋一次」的鬼打牆
    $product = renderReadyProduct(['compliance_passed' => false, 'disclosure_prefix' => null, 'render_id' => 'render_x']);

    expect($product->fresh()->renderBlockers())->toHaveCount(3)
        ->toContain('合規檢查未通過')
        ->toContain('未設定聯盟行銷揭露前綴')
        ->toContain('已有渲染任務進行中');
});
