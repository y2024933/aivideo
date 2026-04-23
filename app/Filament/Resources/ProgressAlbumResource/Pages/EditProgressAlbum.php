<?php

namespace App\Filament\Resources\ProgressAlbumResource\Pages;

use App\Filament\Resources\ProgressAlbumResource;
use Filament\Actions;
use App\Filament\Pages\BaseEditRecord;

class EditProgressAlbum extends BaseEditRecord
{
    protected static string $resource = ProgressAlbumResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
