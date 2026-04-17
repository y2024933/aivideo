<?php

namespace App\Filament\Resources\ProgressAlbumResource\Pages;

use App\Filament\Resources\ProgressAlbumResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProgressAlbum extends EditRecord
{
    protected static string $resource = ProgressAlbumResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
