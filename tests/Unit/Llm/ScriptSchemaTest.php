<?php

declare(strict_types=1);

use Anthropic\Core\Attributes\Required;
use App\Data\Llm\ScriptOutput;
use App\Data\Llm\ScriptSchema;
use App\Data\Llm\ScriptShot;
use App\Enums\KenBurns;
use App\Enums\ShotRole;

/** 讀 ScriptShot 屬性上 #[Required(enum: ...)] 的實際 enum 值（Anthropic 送出去的那一份） */
function anthropicEnum(string $property): array
{
    $attribute = (new ReflectionProperty(ScriptShot::class, $property))->getAttributes(Required::class)[0] ?? null;

    return $attribute?->newInstance()->enumValues ?? [];
}

// --- toGeminiSchema()：結構 ---

it('toGeminiSchema 是帶 properties 與 required 的 object', function () {
    $schema = ScriptSchema::toGeminiSchema();

    expect($schema['type'])->toBe('object')
        ->and(array_keys($schema['properties']))->toBe(['shots', 'caption', 'hashtags'])
        ->and($schema['required'])->toBe(['shots', 'caption', 'hashtags'])
        ->and($schema['description'])->toBe(ScriptSchema::DESCRIPTION);
});

it('shots 是 4–8 個物件，items 含全部 7 個鏡頭欄位且都是必填', function () {
    $shots = ScriptSchema::toGeminiSchema()['properties']['shots'];

    expect($shots['type'])->toBe('array')
        ->and($shots['minItems'])->toBe(ScriptSchema::MIN_SHOTS)
        ->and($shots['maxItems'])->toBe(ScriptSchema::MAX_SHOTS)
        ->and($shots['items']['type'])->toBe('object')
        ->and(array_keys($shots['items']['properties']))->toHaveCount(7)
        ->and(array_keys($shots['items']['properties']))
        ->toBe(['role', 'imageRef', 'kenBurns', 'durationSeconds', 'subtitle', 'voiceoverText', 'transition'])
        ->and($shots['items']['required'])->toBe(array_keys($shots['items']['properties']));
});

it('鏡頭欄位的型別與數值界線符合 Gemini 支援的關鍵字', function () {
    $fields = ScriptSchema::toGeminiSchema()['properties']['shots']['items']['properties'];

    expect($fields['imageRef']['type'])->toBe('integer')
        ->and($fields['imageRef']['minimum'])->toBe(0)
        ->and($fields['durationSeconds']['type'])->toBe('number')
        ->and($fields['durationSeconds']['minimum'])->toBe(ScriptSchema::MIN_DURATION)
        ->and($fields['durationSeconds']['maximum'])->toBe(ScriptSchema::MAX_DURATION)
        ->and($fields['subtitle']['type'])->toBe('string')
        ->and($fields['voiceoverText']['type'])->toBe('string');

    // Gemini 的 structured output 子集不支援 string 的 maxLength，只能靠 description 提示
    expect($fields['subtitle'])->not->toHaveKey('maxLength')
        ->and($fields['subtitle']['description'])->toContain('22');

    // 每個欄位都要有 description，否則 LLM 只看得到欄位名
    foreach ($fields as $name => $field) {
        expect($field['description'])->not->toBeEmpty("{$name} 缺 description");
    }
});

it('hashtags 是 3–8 個字串，caption 是純字串', function () {
    $properties = ScriptSchema::toGeminiSchema()['properties'];

    expect($properties['hashtags'])->toMatchArray([
        'type' => 'array',
        'minItems' => ScriptSchema::MIN_HASHTAGS,
        'maxItems' => ScriptSchema::MAX_HASHTAGS,
        'items' => ['type' => 'string'],
    ])->and($properties['caption']['type'])->toBe('string')
        ->and($properties['caption'])->not->toHaveKey('maxLength');
});

// --- enum 不漂移 ---

it('Gemini schema 的 enum 與 ScriptShot 的 Anthropic attribute 完全一致', function () {
    $fields = ScriptSchema::toGeminiSchema()['properties']['shots']['items']['properties'];

    expect($fields['role']['enum'])->toBe(anthropicEnum('role'))
        ->and($fields['kenBurns']['enum'])->toBe(anthropicEnum('kenBurns'))
        ->and($fields['transition']['enum'])->toBe(anthropicEnum('transition'));

    // attribute 真的有讀到值（反射拿不到時會是空陣列，上面的斷言就會兩邊都空而假性通過）
    expect(anthropicEnum('role'))->toBe(ScriptSchema::ROLES)->not->toBeEmpty();
});

it('kenBurns 的 enum 與 App\Enums\KenBurns 一致，但排除 auto', function () {
    $values = array_column(KenBurns::cases(), 'value');

    expect(array_diff(ScriptSchema::KEN_BURNS, $values))->toBe([])
        ->and(array_values(array_diff($values, ScriptSchema::KEN_BURNS)))->toBe(['auto'])
        ->and(ScriptSchema::KEN_BURNS)->not->toContain('auto');
});

it('role 的 enum 與 App\Enums\ShotRole 一致，但排除 other', function () {
    $values = array_column(ShotRole::cases(), 'value');

    expect(array_diff(ScriptSchema::ROLES, $values))->toBe([])
        ->and(array_values(array_diff($values, ScriptSchema::ROLES)))->toBe(['other']);
});

it('ScriptShot 的常數只是 ScriptSchema 的轉接，不是另抄一份', function () {
    expect(ScriptShot::ROLES)->toBe(ScriptSchema::ROLES)
        ->and(ScriptShot::KEN_BURNS)->toBe(ScriptSchema::KEN_BURNS)
        ->and(ScriptShot::TRANSITIONS)->toBe(ScriptSchema::TRANSITIONS);
});

// --- hydrate() ---

it('hydrate 把完整的 assoc array 組成 ScriptOutput', function () {
    $output = ScriptSchema::hydrate([
        'shots' => [
            ['role' => 'hook', 'imageRef' => 2, 'kenBurns' => 'panUp', 'durationSeconds' => 2.5, 'subtitle' => '出門才發現沒電', 'voiceoverText' => '出門才發現沒電。', 'transition' => 'cut'],
            ['role' => 'cta', 'imageRef' => 0, 'kenBurns' => 'zoomOut', 'durationSeconds' => 3.0, 'subtitle' => '連結放左下', 'voiceoverText' => '', 'transition' => 'crossfade'],
        ],
        'caption' => '  通勤路上想要一點安靜。  ',
        'hashtags' => ['#藍牙耳機', ' 通勤好物 ', '耳機推薦'],
    ]);

    expect($output)->toBeInstanceOf(ScriptOutput::class)
        ->and($output->shots)->toHaveCount(2)
        ->and($output->shots[0]->role)->toBe('hook')
        ->and($output->shots[0]->imageRef)->toBe(2)
        ->and($output->shots[0]->kenBurns)->toBe('panUp')
        ->and($output->shots[0]->durationSeconds)->toBe(2.5)
        ->and($output->shots[0]->subtitle)->toBe('出門才發現沒電')
        ->and($output->shots[0]->transition)->toBe('cut')
        ->and($output->caption)->toBe('通勤路上想要一點安靜。')
        // hashtag 的 # 前綴與空白要被清掉
        ->and($output->hashtags)->toBe(['藍牙耳機', '通勤好物', '耳機推薦']);
});

it('hydrate 在欄位缺失時給安全預設而不是爆掉', function () {
    $output = ScriptSchema::hydrate(['shots' => [[]]]);

    expect($output->shots[0]->role)->toBe('feature')
        ->and($output->shots[0]->imageRef)->toBe(0)
        ->and($output->shots[0]->kenBurns)->toBe('zoomIn')
        ->and($output->shots[0]->durationSeconds)->toBe(3.0)
        ->and($output->shots[0]->subtitle)->toBe('')
        ->and($output->shots[0]->voiceoverText)->toBe('')
        ->and($output->shots[0]->transition)->toBe('crossfade')
        ->and($output->caption)->toBe('')
        ->and($output->hashtags)->toBe([]);
});

it('hydrate 遇到 enum 外的值時 fallback 到合法值', function () {
    $output = ScriptSchema::hydrate(['shots' => [
        ['role' => 'other', 'kenBurns' => 'auto', 'transition' => 'flipHorizontal'],
    ]]);

    expect($output->shots[0]->role)->toBe('feature')
        ->and($output->shots[0]->kenBurns)->toBe('zoomIn')
        ->and($output->shots[0]->transition)->toBe('crossfade');
});

it('hydrate 把超出範圍的秒數與負的 imageRef 夾回界線內', function () {
    $output = ScriptSchema::hydrate(['shots' => [
        ['durationSeconds' => 99.0, 'imageRef' => -3],
        ['durationSeconds' => 0.2],
    ]]);

    expect($output->shots[0]->durationSeconds)->toBe(ScriptSchema::MAX_DURATION)
        ->and($output->shots[0]->imageRef)->toBe(0)
        ->and($output->shots[1]->durationSeconds)->toBe(ScriptSchema::MIN_DURATION);
});

it('hydrate 只取前 8 個 hashtag', function () {
    $output = ScriptSchema::hydrate(['shots' => [[]], 'hashtags' => range(1, 20)]);

    expect($output->hashtags)->toHaveCount(ScriptSchema::MAX_HASHTAGS);
});

it('hydrate 跳過不是物件的鏡頭', function () {
    $output = ScriptSchema::hydrate(['shots' => ['壞資料', ['role' => 'cta'], null]]);

    expect($output->shots)->toHaveCount(1)
        ->and($output->shots[0]->role)->toBe('cta');
});

it('hydrate 在 shots 為空時丟明確例外', function (array $raw) {
    expect(fn () => ScriptSchema::hydrate($raw))
        ->toThrow(RuntimeException::class, '沒有任何鏡頭');
})->with([
    'shots 缺失' => [['caption' => 'x']],
    'shots 為空陣列' => [['shots' => []]],
    'shots 全是壞資料' => [['shots' => ['x', 1]]],
]);

it('hydrate 的產出可以直接 jsonSerialize 寫進 products.script', function () {
    $json = ScriptSchema::hydrate(['shots' => [['role' => 'hook', 'subtitle' => '測試']], 'caption' => 'c', 'hashtags' => ['a']])->jsonSerialize();

    expect($json)->toHaveKeys(['shots', 'caption', 'hashtags'])
        ->and($json['shots'][0]['subtitle'])->toBe('測試');
});
