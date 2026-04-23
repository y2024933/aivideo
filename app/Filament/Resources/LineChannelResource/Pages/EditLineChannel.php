<?php

namespace App\Filament\Resources\LineChannelResource\Pages;

use App\Filament\Resources\LineChannelResource;
use Filament\Actions;
use App\Filament\Pages\BaseEditRecord;

class EditLineChannel extends BaseEditRecord
{
    protected static string $resource = LineChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
