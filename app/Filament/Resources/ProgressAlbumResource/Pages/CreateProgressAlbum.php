<?php

namespace App\Filament\Resources\ProgressAlbumResource\Pages;

use App\Filament\Resources\ProgressAlbumResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProgressAlbum extends CreateRecord
{
    protected static string $resource = ProgressAlbumResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 一般帳號強制使用所屬站台，防止竄改
        if (! auth()->user()->isSuperAdmin()) {
            $data['site_id'] = auth()->user()->sites()->value('sites.id');
        } else {
            $data['site_id'] ??= auth()->user()->sites()->value('sites.id');
        }
        return $data;
    }
}
