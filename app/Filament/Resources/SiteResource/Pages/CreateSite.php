<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use App\Models\Page;
use Filament\Resources\Pages\CreateRecord;

class CreateSite extends CreateRecord
{
    protected static string $resource = SiteResource::class;

    protected function afterCreate(): void
    {
        $pages = [
            ['slug' => 'about', 'title' => '關於我們', 'sort_order' => 1],
            ['slug' => 'projects', 'title' => '建築作品', 'sort_order' => 2],
            ['slug' => 'news', 'title' => '最新消息', 'sort_order' => 3],
            ['slug' => 'services', 'title' => '多元服務', 'sort_order' => 4],
            ['slug' => 'progress', 'title' => '工程進度', 'sort_order' => 5],
            ['slug' => 'contact', 'title' => '聯絡我們', 'sort_order' => 6],
        ];

        foreach ($pages as $page) {
            Page::create([...$page, 'site_id' => $this->record->id]);
        }
    }
}
