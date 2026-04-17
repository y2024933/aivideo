<?php

namespace App\Filament\Resources\ProgressAlbumResource\Pages;

use App\Filament\Resources\ProgressAlbumResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProgressAlbums extends ListRecords
{
    protected static string $resource = ProgressAlbumResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
