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

        // 一般帳號強制使用所屬站台，防止竄改
        if (! $user->isSuperAdmin()) {
            $data['site_id'] = $user->sites()->value('sites.id');
        } else {
            $data['site_id'] ??= $user->sites()->value('sites.id');
        }
        $data['created_by'] = $user?->id;
        $data['updated_by'] = $user?->id;

        return $data;
    }
}
