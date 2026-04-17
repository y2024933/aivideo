<?php

namespace App\Filament\Resources\ProgressUpdateResource\Pages;

use App\Filament\Resources\ProgressUpdateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProgressUpdate extends EditRecord
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
