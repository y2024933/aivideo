<?php

declare(strict_types=1);

use App\Data\ComplianceFinding;
use App\Data\Llm\ScriptOutput;
use App\Data\Llm\ScriptShot;
use App\Enums\ProductStatus;
use App\Jobs\GenerateScriptJob;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shot;
use App\Services\Compliance\AdComplianceChecker;
use App\Services\Contracts\ScriptWriterContract;
use App\Services\Llm\ScriptFields;
use App\Services\Stubs\StubScriptWriter;

/**
 * ScriptFields 是「要送去合規檢查的欄位」的唯一組裝點，被兩條路徑共用：
 *   - GenerateScriptJob（寫稿後，資料還在 ScriptOutput 裡）→ fromOutput()
 *   - ProductResource::checkScript()（checkpoint ② 核准前，資料已在 DB 且 operator 可能改過）→ fromProduct()
 *
 * 兩邊組出不同的欄位集合 = 「產稿時過、核准時莫名被擋」或更糟的「產稿時擋、核准時放行」。
 * 所以這個檔的核心是 parity 測試，其他案例都是在釘住 parity 的前提條件。
 */
function scriptOutput(array $rows, string $caption = '貼文內容', array $hashtags = ['耳機']): ScriptOutput
{
    return new ScriptOutput(
        shots: array_map(fn (array $row) => new ScriptShot(subtitle: $row[0], voiceoverText: $row[1] ?? ''), $rows),
        caption: $caption,
        hashtags: $hashtags,
    );
}

// ─────────────────────────────────────────────────────────────
// 欄位鍵名與形狀
// ─────────────────────────────────────────────────────────────

it('builds S01-style keys per shot plus caption and hashtags', function () {
    $product = Product::factory()->create(['disclosure_prefix' => '【揭露】']);

    $fields = ScriptFields::fromOutput($product, scriptOutput([['第一鏡字幕', '第一鏡配音'], ['第二鏡字幕', '第二鏡配音']]));

    expect(array_keys($fields))->toBe([
        'S01.subtitle', 'S01.voiceover_text',
        'S02.subtitle', 'S02.voiceover_text',
        'caption', 'hashtags',
    ])
        ->and($fields['S01.subtitle'])->toBe('第一鏡字幕')
        ->and($fields['S02.voiceover_text'])->toBe('第二鏡配音');
});

it('zero-pads the shot index so S09 and S10 sort correctly', function () {
    $product = Product::factory()->create();
    $rows = array_map(fn (int $i) => ["第{$i}鏡"], range(1, 11));

    expect(array_slice(array_keys(ScriptFields::fromOutput($product, scriptOutput($rows))), 16, 6))
        ->toBe(['S09.subtitle', 'S09.voiceover_text', 'S10.subtitle', 'S10.voiceover_text', 'S11.subtitle', 'S11.voiceover_text']);
});

it('keys fromProduct by the stored shot_id in shot_order', function () {
    // fromProduct() 刻意用 shot_id 當 key 而不是迴圈索引：ComplianceReport::byShot()
    // 要靠這個 key 把 finding 寫回對應鏡頭的 compliance_flags。
    $product = Product::factory()->create();

    Shot::factory()->for($product)->create(['shot_id' => 'S02', 'shot_order' => 2, 'subtitle' => '第二', 'voiceover_text' => '配二']);
    Shot::factory()->for($product)->create(['shot_id' => 'S01', 'shot_order' => 1, 'subtitle' => '第一', 'voiceover_text' => '配一']);

    $fields = ScriptFields::fromProduct($product);

    expect(array_keys($fields))->toBe(['S01.subtitle', 'S01.voiceover_text', 'S02.subtitle', 'S02.voiceover_text', 'caption', 'hashtags'])
        ->and($fields['S01.subtitle'])->toBe('第一')
        ->and($fields['S02.voiceover_text'])->toBe('配二');
});

// ─────────────────────────────────────────────────────────────
// 揭露前綴：disclosure_required 是 blocking 規則，接錯就永遠擋
// ─────────────────────────────────────────────────────────────

it('prepends the disclosure prefix to the caption with a blank line', function () {
    $product = Product::factory()->create(['disclosure_prefix' => '【利益揭露】本影片含分潤連結。']);

    expect(ScriptFields::fromOutput($product, scriptOutput([['字幕']], caption: '這款耳機我每天在用。'))['caption'])
        ->toBe("【利益揭露】本影片含分潤連結。\n\n這款耳機我每天在用。");
});

it('satisfies the disclosure_required rule, and fails it without the prefix', function () {
    // 這是整個類別存在的理由：揭露句存在 products.disclosure_prefix，貼文存在 caption，
    // 不接起來掃，disclosure_required（blocking）永遠命中 → 任何商品都無法核准。
    $checker = app(AdComplianceChecker::class);
    $output = scriptOutput([['戴上就安靜']], caption: '通勤路上想要一點安靜，分享給同樣怕吵的你。');

    $withPrefix = Product::factory()->create();   // factory 的 disclosure_prefix = config 值

    expect($withPrefix->disclosure_prefix)->toBe(config('compliance.disclosure_prefix'))
        ->and(disclosureFindings($checker->check(ScriptFields::fromOutput($withPrefix, $output))))->toBe([]);

    // 對照組：少了前綴就一定有 blocking finding（證明上面的 [] 不是因為規則沒跑）
    $without = Product::factory()->create(['disclosure_prefix' => null]);
    $findings = disclosureFindings($checker->check(ScriptFields::fromOutput($without, $output)));

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->severity)->toBe('blocking')
        ->and($findings[0]->field)->toBe('caption');
});

/** @return list<ComplianceFinding> */
function disclosureFindings(App\Data\ComplianceReport $report): array
{
    return array_values(array_filter($report->findings, fn (ComplianceFinding $f) => $f->ruleId === 'disclosure_required'));
}

it('does not prepend anything when the prefix is blank', function () {
    foreach ([null, '', '   '] as $prefix) {
        $product = Product::factory()->create(['disclosure_prefix' => $prefix]);

        expect(ScriptFields::fromOutput($product, scriptOutput([['字幕']], caption: '貼文'))['caption'])->toBe('貼文');
    }
});

it('trims the assembled caption', function () {
    $product = Product::factory()->create(['disclosure_prefix' => '  【揭露】  ']);

    // 前綴先 trim、整串再 trim —— 否則 must_be_first_line 會因為開頭空白而命中。
    // ⚠️ 中間那段（貼文自己的前導空白）刻意不動，釘住現行行為：揭露句在第一行就夠了。
    expect(ScriptFields::fromOutput($product, scriptOutput([['字幕']], caption: "  貼文  \n"))['caption'])
        ->toBe("【揭露】\n\n  貼文")
        // 第一行仍是乾淨的揭露句，disclosure_required 不受影響
        ->and(explode("\n", ScriptFields::fromOutput($product, scriptOutput([['字幕']], caption: "  貼文  \n"))['caption'])[0])
        ->toBe('【揭露】');
});

// ─────────────────────────────────────────────────────────────
// hashtags
// ─────────────────────────────────────────────────────────────

it('joins hashtags with a single # each', function () {
    $product = Product::factory()->create();

    expect(ScriptFields::fromOutput($product, scriptOutput([['字幕']], hashtags: ['藍牙耳機', '#通勤好物', '##重複井號']))['hashtags'])
        ->toBe('#藍牙耳機 #通勤好物 #重複井號');
});

it('returns an empty hashtags string when there are none', function () {
    $product = Product::factory()->create();

    expect(ScriptFields::fromOutput($product, scriptOutput([['字幕']], hashtags: []))['hashtags'])->toBe('');
});

// ─────────────────────────────────────────────────────────────
// 空值與缺欄位
// ─────────────────────────────────────────────────────────────

it('still returns caption and hashtags with no shots at all', function () {
    // caption 這個 key 必須永遠存在：AdComplianceChecker::checkDisclosure() 看的是
    // array_key_exists('caption')，key 不在就直接跳過整條 blocking 規則。
    $product = Product::factory()->create(['disclosure_prefix' => '【揭露】']);

    expect(ScriptFields::fromOutput($product, scriptOutput([], caption: '', hashtags: [])))
        ->toBe(['caption' => '【揭露】', 'hashtags' => ''])
        ->and(ScriptFields::fromProduct($product))->toHaveKeys(['caption', 'hashtags']);
});

it('coerces null db columns to empty strings', function () {
    // AdComplianceChecker 的 runRule() 吃 string；fromProduct() 漏 cast 會在 null 上炸
    $product = Product::factory()->create(['caption' => null, 'hashtags' => null, 'disclosure_prefix' => null]);
    Shot::factory()->for($product)->create(['subtitle' => null, 'voiceover_text' => null]);

    $fields = ScriptFields::fromProduct($product);

    expect($fields)->toBe(['S01.subtitle' => '', 'S01.voiceover_text' => '', 'caption' => '', 'hashtags' => ''])
        ->and(array_filter($fields, fn ($v) => ! is_string($v)))->toBe([]);
});

// ─────────────────────────────────────────────────────────────
// Parity：兩條路徑必須組出同一份東西
// ─────────────────────────────────────────────────────────────

it('produces identical fields from the output and from the persisted shots', function (string $audioMode) {
    $product = Product::factory()->status(ProductStatus::ProductApproved)->create(['audio_mode' => $audioMode]);

    foreach (range(1, 4) as $i) {
        ProductImage::factory()->for($product)->create(['sort_order' => $i, 'is_primary' => $i === 1]);
    }

    $output = StubScriptWriter::cleanOutput($audioMode === 'tts');
    app()->instance(ScriptWriterContract::class, new StubScriptWriter($output));
    dispatch_sync(new GenerateScriptJob($product->id));

    $fromOutput = ScriptFields::fromOutput($product, $output);
    $fromProduct = ScriptFields::fromProduct($product->refresh());

    // key 集合不同 → 某些欄位只在一邊被掃
    expect(array_keys($fromProduct))->toBe(array_keys($fromOutput))
        // 值也必須相同，否則同一份稿在兩個 checkpoint 會得到不同的合規結果
        ->and($fromProduct)->toBe($fromOutput);

    // 再往下一層：同一個 checker 跑兩邊，報告的命中點必須一模一樣
    $checker = app(AdComplianceChecker::class);
    $fingerprint = fn (array $fields) => array_map(
        fn (ComplianceFinding $f) => "{$f->field}|{$f->ruleId}|{$f->offset}|{$f->matched}",
        $checker->check($fields, (string) $product->compliance_profile)->findings,
    );

    expect($fingerprint($fromProduct))->toBe($fingerprint($fromOutput));
})->with(['none', 'tts', 'bgm_only']);

it('keeps parity after the operator edits a subtitle in checkpoint 2', function () {
    // operator 在 ② 改字後，fromProduct() 必須反映新字（舊報告描述的是 LLM 當初寫的字）
    $product = Product::factory()->create(['caption' => '原始貼文']);
    $shot = Shot::factory()->for($product)->create(['subtitle' => '原始字幕']);

    expect(ScriptFields::fromProduct($product)['S01.subtitle'])->toBe('原始字幕');

    $shot->update(['subtitle' => '保證三天見效']);
    $product->update(['caption' => '改過的貼文']);

    $fields = ScriptFields::fromProduct($product->refresh());

    expect($fields['S01.subtitle'])->toBe('保證三天見效')
        ->and($fields['caption'])->toContain('改過的貼文')
        // 對照組：改壞的字真的會被 checker 抓到，證明這條路徑有把新字送進去
        ->and(app(AdComplianceChecker::class)->check($fields)->blocking())->not->toBe([]);
});

it('drops voiceover text from the approval-time scan when audio_mode is none', function () {
    // ⚠️ 已知的 parity 落差（回報用，非理想行為）：
    //    audio_mode = none 時 GenerateScriptJob::writeShots() 不把 voiceoverText 寫進 DB，
    //    但 fromOutput() 照樣把它送進合規掃描。所以若 LLM 在 none 模式仍回了配音稿，
    //    寫稿當下會掃它（可能擋下整份稿），checkpoint ② 重掃時卻掃不到。
    //    這條測試只是釘住現行行為 —— 修法是 fromOutput() 也依 audio_mode 過濾。
    $product = Product::factory()->status(ProductStatus::ProductApproved)->create(['audio_mode' => 'none']);

    foreach (range(1, 4) as $i) {
        ProductImage::factory()->for($product)->create(['sort_order' => $i, 'is_primary' => $i === 1]);
    }

    $output = StubScriptWriter::cleanOutput(withVoiceover: true);
    app()->instance(ScriptWriterContract::class, new StubScriptWriter($output));
    dispatch_sync(new GenerateScriptJob($product->id));

    $fromOutput = ScriptFields::fromOutput($product, $output);
    $fromProduct = ScriptFields::fromProduct($product->refresh());

    expect(array_keys($fromProduct))->toBe(array_keys($fromOutput))   // key 集合仍相同
        ->and($fromOutput['S01.voiceover_text'])->not->toBe('')
        ->and($fromProduct['S01.voiceover_text'])->toBe('');          // 值不同 → 兩邊掃到的文字量不同
});

it('⚠️ 商品自訂的揭露前綴也要能通過合規檢查', function () {
    // 回歸測試：ScriptFields 組 caption 用的是 $product->disclosure_prefix，
    // 而 AdComplianceChecker 原本比對 config 的全域預設 → operator 只要改過前綴
    // （哪怕只是調語氣），合規就永遠 blocking，而錯誤訊息是「貼文第一行必須有揭露句」，
    // 他看得到揭露句就在第一行，完全無法自救。
    $custom = '【利益揭露】本片含蝦皮分潤連結，購買不影響您的價格。';
    $product = Product::factory()->create(['disclosure_prefix' => $custom, 'caption' => '通勤族必備的降噪耳機']);

    $report = app(AdComplianceChecker::class)->check(
        ScriptFields::fromProduct($product),
        '3c',
        ['disclosure_prefix' => $custom],
    );

    expect(array_filter($report->findings, fn ($f) => $f->ruleId === 'disclosure_required'))->toBeEmpty();
});

it('完全沒有揭露前綴時仍然會被擋（上一條的對照組）', function () {
    $product = Product::factory()->create(['disclosure_prefix' => '', 'caption' => '通勤族必備的降噪耳機']);

    $report = app(AdComplianceChecker::class)->check(
        ScriptFields::fromProduct($product),
        '3c',
        ['disclosure_prefix' => ''],
    );

    expect(array_filter($report->findings, fn ($f) => $f->ruleId === 'disclosure_required'))->not->toBeEmpty();
});
