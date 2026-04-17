<?php

namespace App\Filament\Resources\SiteSettingResource\Pages;

use App\Filament\Resources\SiteSettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSiteSetting extends CreateRecord
{
    protected static string $resource = SiteSettingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] ??= auth()->user()?->sites()->value('sites.id');

        return $data;
    }
}
