<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BrowserTaskStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Degraded = 'degraded';
    case Failed = 'failed';
    case NeedsManual = 'needs_manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => '等待中',
            self::Running => '執行中',
            self::Succeeded => '成功',
            self::Degraded => '降級成功',
            self::Failed => '失敗',
            self::NeedsManual => '需人工介入',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Running => 'info',
            self::Succeeded => 'success',
            self::Degraded => 'warning',
            self::Failed => 'danger',
            self::NeedsManual => 'danger',
        };
    }
}
