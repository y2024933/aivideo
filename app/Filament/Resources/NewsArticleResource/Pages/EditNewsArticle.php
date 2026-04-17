<?php

namespace App\Filament\Resources\NewsArticleResource\Pages;

use App\Filament\Resources\NewsArticleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNewsArticle extends EditRecord
{
    protected static string $resource = NewsArticleResource::class;

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
