<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $role = $this->data['role'] ?? null;
        if ($role) {
            $this->record->syncRoles([$role]);
        }

        // 一般帳號手動 sync 單一站台
        $siteId = $this->data['site_id_single'] ?? null;
        if ($role === 'site_admin' && $siteId) {
            $this->record->sites()->sync([$siteId]);
        }
    }
}
