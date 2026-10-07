<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Ken Burns 運鏡效果。value 用 camelCase，與 Remotion 端的 JS key 一對一。
 */
enum KenBurns: string implements HasLabel
{
    case None = 'none';
    case ZoomIn = 'zoomIn';
    case ZoomOut = 'zoomOut';
    case PanLeft = 'panLeft';
    case PanRight = 'panRight';
    case PanUp = 'panUp';
    case PanDown = 'panDown';
    case ZoomInPanUp = 'zoomInPanUp';
    case ZoomOutPanDown = 'zoomOutPanDown';
    case Auto = 'auto';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => '靜止',
            self::ZoomIn => '推近',
            self::ZoomOut => '拉遠',
            self::PanLeft => '左移',
            self::PanRight => '右移',
            self::PanUp => '上移',
            self::PanDown => '下移',
            self::ZoomInPanUp => '推近＋上移',
            self::ZoomOutPanDown => '拉遠＋下移',
            self::Auto => '自動輪替',
        };
    }
}
