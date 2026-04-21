<?php

namespace App\Filament\Resources\LineTargetResource\Pages;

use App\Filament\Resources\LineTargetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLineTarget extends EditRecord
{
    protected static string $resource = LineTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
