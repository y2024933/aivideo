<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 國語數字處理。
 *
 * 原本埋在 AzureTts 裡，但「阿拉伯數字 → 中文」對價格播報、字幕排版都有用，
 * 跟 TTS 供應商無關，抽出來才能在 LLM prompt 與文案組裝時重用。
 */
final class MandarinNumber
{
    private const DIGITS = ['零', '一', '二', '三', '四', '五', '六', '七', '八', '九'];

    /** 文字中的阿拉伯數字全部轉成中文（支援 0-9999，超出範圍原樣保留） */
    public static function toChinese(string $text): string
    {
        return preg_replace_callback('/(\d+)/', fn (array $m) => self::numberToChinese((int) $m[1]), $text);
    }

    /** 價格播報用：'NT$1,290'。四捨五入到整數並加千分位。 */
    public static function formatPrice(float $price): string
    {
        return 'NT$' . number_format(round($price));
    }

    /** 單一數字轉中文 */
    private static function numberToChinese(int $num): string
    {
        if ($num < 0 || $num > 9999) {
            return (string) $num; // 超出範圍不轉換
        }

        if ($num <= 10) {
            return $num === 10 ? '十' : self::DIGITS[$num];
        }

        $result = '';

        // 千位
        if ($num >= 1000) {
            $result .= self::DIGITS[(int) ($num / 1000)] . '千';
            $num %= 1000;
            if ($num > 0 && $num < 100) {
                $result .= '零';
            }
        }

        // 百位
        if ($num >= 100) {
            $result .= self::DIGITS[(int) ($num / 100)] . '百';
            $num %= 100;
            if ($num > 0 && $num < 10) {
                $result .= '零';
            }
        }

        // 十位
        if ($num >= 10) {
            $tens = (int) ($num / 10);
            // 十幾不需要「一十」，直接「十」（僅用於整體數字就是十幾時）
            $result .= ($tens === 1 && $result === '' ? '' : self::DIGITS[$tens]) . '十';
            $num %= 10;
        }

        // 個位
        if ($num > 0) {
            $result .= self::DIGITS[$num];
        }

        return $result;
    }
}
