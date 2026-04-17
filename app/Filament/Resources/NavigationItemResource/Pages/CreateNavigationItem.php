<?php

namespace App\Filament\Resources\NavigationItemResource\Pages;

use App\Filament\Resources\NavigationItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNavigationItem extends CreateRecord
{
    protected static string $resource = NavigationItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] ??= auth()->user()?->sites()->value('sites.id');

        return $data;
    }
}
