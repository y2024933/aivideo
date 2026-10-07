<?php

declare(strict_types=1);

use App\Support\MandarinNumber;

// --- 數字轉中文（原本在 AzureTtsTest，隨 MandarinNumber 抽出一起搬過來）---

it('converts single digits to Chinese', function () {
    expect(MandarinNumber::toChinese('0'))->toBe('零');
    expect(MandarinNumber::toChinese('1'))->toBe('一');
    expect(MandarinNumber::toChinese('9'))->toBe('九');
});

it('converts teens to Chinese', function () {
    expect(MandarinNumber::toChinese('10'))->toBe('十');
    expect(MandarinNumber::toChinese('11'))->toBe('十一');
    expect(MandarinNumber::toChinese('19'))->toBe('十九');
});

it('converts tens to Chinese', function () {
    expect(MandarinNumber::toChinese('20'))->toBe('二十');
    expect(MandarinNumber::toChinese('35'))->toBe('三十五');
    expect(MandarinNumber::toChinese('99'))->toBe('九十九');
});

it('converts hundreds to Chinese', function () {
    expect(MandarinNumber::toChinese('100'))->toBe('一百');
    expect(MandarinNumber::toChinese('105'))->toBe('一百零五');
    expect(MandarinNumber::toChinese('110'))->toBe('一百一十');
    expect(MandarinNumber::toChinese('999'))->toBe('九百九十九');
});

it('converts thousands to Chinese', function () {
    expect(MandarinNumber::toChinese('1000'))->toBe('一千');
    expect(MandarinNumber::toChinese('1001'))->toBe('一千零一');
    expect(MandarinNumber::toChinese('1010'))->toBe('一千零一十');
    expect(MandarinNumber::toChinese('1100'))->toBe('一千一百');
    expect(MandarinNumber::toChinese('9999'))->toBe('九千九百九十九');
});

it('leaves numbers outside 0-9999 untouched', function () {
    expect(MandarinNumber::toChinese('10000'))->toBe('10000');
});

it('converts numbers within text context', function () {
    expect(MandarinNumber::toChinese('11 樓'))->toBe('十一 樓');
    expect(MandarinNumber::toChinese('位於 3 樓的 200 坪空間'))->toBe('位於 三 樓的 二百 坪空間');
});

// --- 價格格式 ---

it('formats price with thousand separators', function () {
    expect(MandarinNumber::formatPrice(1290.0))->toBe('NT$1,290');
    expect(MandarinNumber::formatPrice(1290.5))->toBe('NT$1,291');
    expect(MandarinNumber::formatPrice(0.0))->toBe('NT$0');
    expect(MandarinNumber::formatPrice(1234567.4))->toBe('NT$1,234,567');
});
