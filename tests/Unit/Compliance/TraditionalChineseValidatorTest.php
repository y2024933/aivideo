<?php

declare(strict_types=1);

use App\Services\Compliance\TraditionalChineseValidator;

beforeEach(function () {
    $this->validator = new TraditionalChineseValidator();
});

/*
|--------------------------------------------------------------------------
| 負向測試：這些全部是合法臺灣用字，一個都不能誤報
|--------------------------------------------------------------------------
| 誤判比漏判更擾人 —— operator 每打一次「台北」被紅標，三天後就會關掉整個功能。
*/
it('does not flag legitimate Taiwanese text', function (string $text) {
    expect($this->validator->isClean($text))->toBeTrue();
})->with([
    '台北', '台灣製', '一台', '公里', '里長', '不干擾', '干涉', '只要', '只剩', '云云', '皇后',
    '系統', '系列', '才發現', '棉布', '宣布', '占星', '控制', '機制', '意志', '征服', '木板',
    '面板', '面膜', '面料', '小丑', '卷裝', '托盤', '吊飾', '划船', '可回收', '保溫杯', '廣泛',
    '游泳', '周圍', '周到', '升級', '公升', '方向', '丰采', '蒙古包', '迭代', '防污', '沉穩',
    '著名', '顯著', '范先生', '于小姐', '周杰倫', '沈教授', '表格', '山谷', '秋天', '丈夫',
]);

it('does not flag common words that collide with glyph variant self-mappings', function (string $text) {
    expect($this->validator->findGlyphVariants($text))->toBe([]);
})->with(['什麼', '怎麼辦', '梁先生', '橋梁']);

/*
|--------------------------------------------------------------------------
| 簡體字
|--------------------------------------------------------------------------
*/
it('finds simplified chars with mb character offsets', function () {
    $findings = $this->validator->findSimplifiedChars('降噪开启后');

    expect($findings)->toHaveCount(2)
        ->and($findings[0])->toMatchArray(['char' => '开', 'offset' => 2, 'suggestions' => ['開']])
        ->and($findings[1])->toMatchArray(['char' => '启', 'offset' => 3, 'suggestions' => ['啟']]);
});

it('keeps every candidate when one simplified char maps to several traditional ones', function () {
    $findings = $this->validator->findSimplifiedChars('头发');

    expect($findings)->toHaveCount(2)
        ->and($findings[1]['char'])->toBe('发')
        ->and($findings[1]['suggestions'])->toBe(['發', '髮']);
});

it('flags boundary chars with low confidence', function () {
    expect($this->validator->findSimplifiedChars('什么')[0])->toMatchArray(['char' => '么', 'confidence' => 'low']);
});

it('finds simplified chars that are also non Taiwanese glyphs', function () {
    expect(collect($this->validator->findSimplifiedChars('没有户口'))->pluck('char')->all())->toBe(['没', '户']);
});

/*
|--------------------------------------------------------------------------
| 大陸用語 / 應刪除用語 / 字形
|--------------------------------------------------------------------------
*/
it('finds mainland terms written in traditional chars', function () {
    $findings = $this->validator->findMainlandTerms('這個視頻的質量很好');

    expect($findings)->toHaveCount(2)
        ->and($findings[0])->toMatchArray(['term' => '視頻', 'offset' => 2, 'length' => 2, 'suggestion' => '影片'])
        ->and($findings[1])->toMatchArray(['term' => '質量', 'offset' => 5, 'suggestion' => '品質']);
});

it('prefers the longest mainland term', function () {
    $findings = $this->validator->findMainlandTerms('筆記本電腦');

    expect($findings)->toHaveCount(1)
        ->and($findings[0]['term'])->toBe('筆記本電腦')
        ->and($findings[0]['suggestion'])->toBe('筆記型電腦');
});

it('finds banned phrases', function () {
    expect($this->validator->findBannedPhrases('閉眼入啦')[0])->toMatchArray(['phrase' => '閉眼入', 'offset' => 0, 'length' => 3]);
});

it('finds non Taiwanese glyph variants', function () {
    $findings = $this->validator->findGlyphVariants('着裝裏面');

    expect($findings)->toHaveCount(2)
        ->and($findings[0])->toMatchArray(['char' => '着', 'offset' => 0, 'standard' => '著'])
        ->and($findings[1])->toMatchArray(['char' => '裏', 'offset' => 2, 'standard' => '裡']);
});

/*
|--------------------------------------------------------------------------
| 白名單
|--------------------------------------------------------------------------
*/
it('skips whitelisted brand names', function () {
    expect($this->validator->isClean('华为手機開箱'))->toBeTrue();
});

it('skips alphanumeric model numbers', function () {
    expect($this->validator->isClean('ABC-123 Pro Max'))->toBeTrue();
});

it('keeps offsets correct after whitelist masking', function () {
    expect($this->validator->findSimplifiedChars('华为的质量')[0])->toMatchArray(['char' => '质', 'offset' => 3]);
});

/*
|--------------------------------------------------------------------------
| 輔助方法
|--------------------------------------------------------------------------
*/
it('builds retry feedback with field, position and candidates', function () {
    $feedback = $this->validator->buildRetryFeedback([
        'S02.subtitle' => '降噪开启后头发',
        'caption' => '性價比超高閉眼入',
    ]);

    expect($feedback)
        ->toContain('- S02.subtitle 第 3 字「开」是簡體字，應為「開」')
        ->toContain('候選為「發」或「髮」，請依語意選擇')
        ->toContain('- caption 使用大陸用語「性價比」，應改為「CP 值」')
        ->toContain('- caption 含應刪除用語「閉眼入」');
});

it('suggests traditional text for the manual fill button only', function () {
    expect($this->validator->suggestTraditional('质量'))->toBe('質量')
        ->and($this->validator->suggestTraditional('着裝'))->toBe('著裝')
        // 一簡對多繁只取第一候選，這就是為什麼此方法不能進自動流程
        ->and($this->validator->suggestTraditional('头发'))->toBe('頭發');
});

it('leaves whitelisted segments untouched when suggesting', function () {
    expect($this->validator->suggestTraditional('华为的质量'))->toBe('华为的質量');
});

it('reports dirty text as not clean', function (string $text) {
    expect($this->validator->isClean($text))->toBeFalse();
})->with(['质量', '視頻', '閉眼入', '着裝']);
