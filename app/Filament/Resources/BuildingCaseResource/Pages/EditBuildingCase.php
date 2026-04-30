<?php

namespace App\Filament\Resources\BuildingCaseResource\Pages;

use App\Filament\Resources\BuildingCaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBuildingCase extends EditRecord
{
    protected static string $resource = BuildingCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
