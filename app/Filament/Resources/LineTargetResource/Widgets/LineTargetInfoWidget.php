<?php

namespace App\Filament\Resources\LineTargetResource\Widgets;

use Filament\Widgets\Widget;

class LineTargetInfoWidget extends Widget
{
    protected static string $view = 'filament.widgets.line-target-info';

    protected int | string | array $columnSpan = 'full';
}
