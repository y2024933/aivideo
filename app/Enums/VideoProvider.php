<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum VideoProvider: string implements HasLabel
{
    case None = 'none';
    case Kling = 'kling';
    case Dola = 'dola';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => '純商品圖 Ken Burns',
            self::Kling => 'Kling AI 動畫',
            self::Dola => 'Dola AI',
        };
    }
}
