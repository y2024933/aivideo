<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BrowserStrategy: string implements HasLabel
{
    case Xhr = 'xhr';
    case Dom = 'dom';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Xhr => 'XHR 攔截',
            self::Dom => 'DOM 解析',
            self::Manual => '人工輸入',
        };
    }
}
