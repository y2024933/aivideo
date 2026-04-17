<?php

namespace App\Filament\Resources\ProgressAlbumResource\Pages;

use App\Filament\Resources\ProgressAlbumResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProgressAlbum extends CreateRecord
{
    protected static string $resource = ProgressAlbumResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['site_id'])) {
            $data['site_id'] = auth()->user()?->sites()->value('sites.id');
        }
        return $data;
    }
}
