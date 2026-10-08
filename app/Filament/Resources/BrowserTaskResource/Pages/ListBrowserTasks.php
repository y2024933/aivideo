<?php

declare(strict_types=1);

namespace App\Filament\Resources\BrowserTaskResource\Pages;

use App\Filament\Resources\BrowserTaskResource;
use Filament\Resources\Pages\ListRecords;

final class ListBrowserTasks extends ListRecords
{
    protected static string $resource = BrowserTaskResource::class;
}
