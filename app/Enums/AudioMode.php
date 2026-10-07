<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AudioMode: string implements HasLabel
{
    case None = 'none';
    case Tts = 'tts';
    case BgmOnly = 'bgm_only';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => '無配音',
            self::Tts => 'AI 配音',
            self::BgmOnly => '只有背景音樂',
        };
    }
}
