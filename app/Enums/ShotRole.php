<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ShotRole: string implements HasLabel
{
    case Hook = 'hook';
    case Pain = 'pain';
    case Feature = 'feature';
    case Proof = 'proof';
    case Cta = 'cta';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hook => '開場鉤子',
            self::Pain => '痛點',
            self::Feature => '賣點',
            self::Proof => '評價佐證',
            self::Cta => '行動呼籲',
            self::Other => '其他',
        };
    }
}
