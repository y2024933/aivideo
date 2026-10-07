<?php

declare(strict_types=1);

use App\Data\Llm\ScriptShot;
use App\Enums\KenBurns;
use App\Enums\ShotRole;
use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Compliance\AdComplianceChecker;
use App\Services\Llm\ScriptPromptBuilder;

beforeEach(function () {
    $this->builder = app(ScriptPromptBuilder::class);
});

// --- system：三個 block 與 cache 斷點 ---

it('回傳三個 content block，cache 斷點在最後一個', function () {
    $blocks = $this->builder->system('3c');

    expect($blocks)->toHaveCount(3)
        ->and($blocks[0])->not->toHaveKey('cache_control')
        ->and($blocks[1])->not->toHaveKey('cache_control')
        ->and($blocks[2]['cache_control'])->toBe(['type' => 'ephemeral'])
        ->and(array_column($blocks, 'type'))->toBe(['text', 'text', 'text']);
});

it('system 總長穩定超過 prompt cache 的 1024 token 門檻', function () {
    $blocks = $this->builder->system('3c');

    // 中文 1 字 >= 1 token，用字元數粗估即可。block 3 自己就要夠大，
    // 否則 cache_control 會被 API 靜默忽略（不報錯，只是永遠 miss）。
    expect(mb_strlen($blocks[2]['text']))->toBeGreaterThanOrEqual(3000)
        ->and(mb_strlen(implode('', array_column($blocks, 'text'))))->toBeGreaterThanOrEqual(5000);
});

it('block 1 明確禁止制式開場並要求 proof 鏡不得編造', function () {
    $block = $this->builder->system('3c')[0]['text'];

    expect($block)->toContain('大家好')
        ->and($block)->toContain('今天要介紹')
        ->and($block)->toContain('不得')
        ->and($block)->toContain('22');
});

// --- block 2：繁中規範 ---

it('block 2 的大陸用語對照表來自 mainland_terms 資料檔', function () {
    $block = $this->builder->system('3c')[1]['text'];
    $terms = require resource_path('data/compliance/mainland_terms.php');

    expect($block)->toContain('視頻→影片')
        ->and($block)->toContain('質量→品質')
        ->and($block)->toContain('性價比→CP 值');

    // 資料檔 99 筆全部要出現，不可手抄一份子集
    foreach ($terms as $term => $taiwan) {
        expect($block)->toContain("{$term}→{$taiwan}");
    }
});

it('block 2 要求全形標點與禁止簡體字', function () {
    $block = $this->builder->system('3c')[1]['text'];

    expect($block)->toContain('簡體字')
        ->and($block)->toContain('全形');
});

// --- block 3：法規紅線與 profile 繼承 ---

it('block 3 會把 profile 的 extends 繼承鏈一起展開', function () {
    $supplement = $this->builder->complianceRules('supplement');
    $general = app(AdComplianceChecker::class)->resolveRules('general');

    // supplement extends food extends general
    expect($supplement)->toContain('health_food_function')   // supplement 自己的
        ->and($supplement)->toContain('health_claim')        // food 繼承來的
        ->and($supplement)->toContain('guarantee');          // general 繼承來的

    foreach ($general as $ruleId) {
        if ($ruleId !== 'disclosure_required') {
            expect($supplement)->toContain($ruleId);
        }
    }
});

it('block 3 每條規則都帶法規依據與建議替代詞', function () {
    $block = $this->builder->complianceRules('3c');

    expect($block)->toContain('公平交易法 §21')
        ->and($block)->toContain('法規依據：')
        ->and($block)->toContain('建議替代寫法：')
        ->and($block)->toContain('禁用詞')
        ->and($block)->toContain('保證')           // guarantee 的禁用詞
        ->and($block)->toContain('spec_overclaim'); // 3c 專屬規則
});

it('block 3 不教 LLM 寫揭露前綴（那是系統自動加的）', function () {
    expect($this->builder->complianceRules('3c'))->not->toContain('disclosure_required');
});

it('被封鎖的分類會在 block 3 明講不開放製作', function () {
    expect($this->builder->complianceRules('restricted'))->toContain('不開放製作影片');
});

// --- user prompt ---

it('user prompt 含商品資料、價格與圖片索引清單', function () {
    $product = Product::factory()->create(['price' => 1290, 'price_before_discount' => 1990]);
    ProductImage::factory()->for($product)->create(['sort_order' => 1, 'is_primary' => true]);
    ProductImage::factory()->for($product)->portrait()->create(['sort_order' => 2]);

    $prompt = $this->builder->user($product->refresh());

    expect($prompt)->toContain($product->title)
        ->and($prompt)->toContain('NT$1,290')
        ->and($prompt)->toContain('NT$1,990')
        ->and($prompt)->toContain('0: 主圖 1000x1000')
        ->and($prompt)->toContain('1: 商品圖 1080x1920')
        ->and($prompt)->toContain('直式長圖')
        ->and($prompt)->toContain('4.80 顆星')
        ->and($prompt)->toContain('30 秒');
});

it('沒有評分與銷量時明確要求不要寫 proof 鏡頭', function () {
    $product = Product::factory()->create(['rating_star' => null, 'rating_count' => null, 'historical_sold' => null]);

    expect($this->builder->user($product))->toContain('不要產生 proof 鏡頭');
});

it('標題含簡體字時在 user prompt 加註提示', function () {
    $clean = Product::factory()->create(['title_has_simplified' => false]);
    $dirty = Product::factory()->create(['title_has_simplified' => true]);

    expect($this->builder->user($dirty))->toContain('原標題含簡體字')
        ->and($this->builder->user($clean))->not->toContain('原標題含簡體字');
});

it('配音模式會要求填 voiceoverText，無配音則要求留空', function () {
    expect($this->builder->user(Product::factory()->withTts()->create()))->toContain('voiceoverText 必填')
        ->and($this->builder->user(Product::factory()->create()))->toContain('voiceoverText 一律回空字串');
});

it('描述節錄不超過 800 字', function () {
    $product = Product::factory()->create(['description' => str_repeat('規', 2000)]);

    expect(mb_substr_count($this->builder->user($product), '規'))->toBeLessThanOrEqual(810);
});

it('帶合規報告重試時列出違規清單與法條', function () {
    $product = Product::factory()->create();
    $report = app(AdComplianceChecker::class)->check([
        'S01.subtitle' => '保證最有效',
        'caption' => '這款讲解得很清楚',
    ], '3c');

    $prompt = $this->builder->user($product, $report, "- S01.subtitle 第 1 字「这」是簡體字，應為「這」");

    expect($prompt)->toContain('你上一次的輸出違反規則')
        ->and($prompt)->toContain('S01.subtitle')
        ->and($prompt)->toContain('保證')
        ->and($prompt)->toContain('公平交易法')
        ->and($prompt)->toContain('繁中逐字修正')
        ->and($prompt)->toContain('是簡體字')
        ->and($prompt)->toContain('重新輸出完整');
});

it('沒有重試資訊時不會出現重試區塊', function () {
    expect($this->builder->user(Product::factory()->create()))->not->toContain('你上一次的輸出違反規則');
});

// --- schema 常數與 enum 必須同步 ---

it('ScriptShot 的常數與 App\Enums 保持同步', function () {
    // 比「集合」而不是順序：schema 的 enum 順序不影響正確性，少一個值才是 bug
    expect(array_diff(ScriptShot::ROLES, array_column(ShotRole::cases(), 'value')))->toBe([])
        ->and(array_diff(array_column(ShotRole::cases(), 'value'), [...ScriptShot::ROLES, 'other']))->toBe([])
        ->and(array_diff(ScriptShot::KEN_BURNS, array_column(KenBurns::cases(), 'value')))->toBe([])
        ->and(array_diff(array_column(KenBurns::cases(), 'value'), [...ScriptShot::KEN_BURNS, 'auto']))->toBe([])
        ->and(array_diff(ScriptShot::TRANSITIONS, array_keys(ProductResource::TRANSITIONS)))->toBe([]);
});
