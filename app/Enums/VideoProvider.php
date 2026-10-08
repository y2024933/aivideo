<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * B-roll 動畫供應商。
 *
 * 預設 None：純商品圖 + Ken Burns 運鏡不花一毛錢，也不會有 AI 把商品畫歪的風險
 * （商品本體變形就是假廣告）。要 AI 動畫才改成 Kling。
 *
 * ⚠️ Stub 只給測試／stub 模式用，不會寫進 products.video_provider 或
 *    shots.video_provider（兩欄的 DB enum 只收 none／kling／dola），
 *    也不會出現在 VideoGeneratorFactory::availableOptions() 的下拉選項裡。
 */
enum VideoProvider: string implements HasLabel
{
    case None = 'none';
    case Kling = 'kling';
    case Dola = 'dola';
    case Stub = 'stub';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => '純商品圖 Ken Burns',
            self::Kling => 'Kling AI 動畫',
            self::Dola => 'Dola AI',
            self::Stub => 'Stub（測試用）',
        };
    }

    /**
     * operator 可以存進 DB 的選項（排除 Stub）
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return [self::None, self::Kling, self::Dola];
    }
}
