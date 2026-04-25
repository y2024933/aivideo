<?php

namespace App\Filament\Resources\NewsArticleResource\Pages;

use App\Filament\Resources\NewsArticleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsArticle extends CreateRecord
{
    protected static string $resource = NewsArticleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 一般帳號強制使用所屬站台，防止竄改
        if (! auth()->user()->isSuperAdmin()) {
            $data['site_id'] = auth()->user()->sites()->value('sites.id');
        } else {
            $data['site_id'] ??= auth()->user()->sites()->value('sites.id');
        }
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
