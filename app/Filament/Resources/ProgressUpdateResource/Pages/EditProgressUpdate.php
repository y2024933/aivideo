<?php

namespace App\Filament\Resources\ProgressUpdateResource\Pages;

use App\Filament\Resources\ProgressUpdateResource;
use Filament\Actions;
use App\Filament\Pages\BaseEditRecord;

class EditProgressUpdate extends BaseEditRecord
{
    protected static string $resource = ProgressUpdateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
