<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ComplianceSeverity: string implements HasLabel
{
    case Blocking = 'blocking';
    case Warning = 'warning';

    public function getLabel(): string
    {
        return match ($this) {
            self::Blocking => '阻擋',
            self::Warning => '警告',
        };
    }
}
