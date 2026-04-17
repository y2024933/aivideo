<?php

namespace App\Filament\Resources\ProgressUpdateResource\Pages;

use App\Filament\Resources\ProgressUpdateResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateProgressUpdate extends CreateRecord
{
    protected static string $resource = ProgressUpdateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] ??= auth()->user()?->sites()->value('sites.id');
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
