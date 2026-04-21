<?php

namespace App\Filament\Resources\LineChannelResource\Pages;

use App\Filament\Resources\LineChannelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLineChannel extends EditRecord
{
    protected static string $resource = LineChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
