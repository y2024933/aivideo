<?php

namespace App\Filament\Resources\LineTargetResource\Pages;

use App\Filament\Resources\LineTargetResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\IconPosition;

class ListLineTargets extends ListRecords
{
    protected static string $resource = LineTargetResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            LineTargetResource\Widgets\LineTargetInfoWidget::class,
        ];
    }
}
