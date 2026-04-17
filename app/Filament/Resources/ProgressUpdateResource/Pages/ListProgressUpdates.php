<?php

namespace App\Filament\Resources\ProgressUpdateResource\Pages;

use App\Filament\Resources\ProgressUpdateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProgressUpdates extends ListRecords
{
    protected static string $resource = ProgressUpdateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
