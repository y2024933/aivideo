<?php

namespace App\Filament\Resources\NavigationItemResource\Pages;

use App\Filament\Resources\NavigationItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNavigationItem extends CreateRecord
{
    protected static string $resource = NavigationItemResource::class;

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
