<?php

namespace App\Filament\Resources\BuildingCaseResource\Pages;

use App\Filament\Resources\BuildingCaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBuildingCases extends ListRecords
{
    protected static string $resource = BuildingCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
