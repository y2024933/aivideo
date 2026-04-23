<?php

namespace App\Filament\Resources\LineTargetResource\Pages;

use App\Filament\Resources\LineTargetResource;
use Filament\Actions;
use App\Filament\Pages\BaseEditRecord;

class EditLineTarget extends BaseEditRecord
{
    protected static string $resource = LineTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
