<?php

namespace App\Filament\Resources\MediaAssetResource\Pages;

use App\Filament\Resources\MediaAssetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMediaAsset extends CreateRecord
{
    protected static string $resource = MediaAssetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        $data['site_id'] ??= $user?->sites()->value('sites.id');
        $data['uploaded_by'] = $user?->id;
        $data['disk'] = 'public';

        return $data;
    }
}
