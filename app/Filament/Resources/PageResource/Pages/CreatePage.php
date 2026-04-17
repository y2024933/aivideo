<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        $data['site_id'] ??= $user?->sites()->value('sites.id');
        $data['created_by'] = $user?->id;
        $data['updated_by'] = $user?->id;

        return $data;
    }
}
