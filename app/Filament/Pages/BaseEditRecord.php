<?php

namespace App\Filament\Pages;

use Filament\Resources\Pages\EditRecord;

class BaseEditRecord extends EditRecord
{
    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('index');
    }
}
