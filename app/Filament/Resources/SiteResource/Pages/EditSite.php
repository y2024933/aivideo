<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use Filament\Actions;
use App\Filament\Pages\BaseEditRecord;

class EditSite extends BaseEditRecord
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('toggleActive')
                ->label(fn () => $this->record->is_active ? '停用站台' : '啟用站台')
                ->icon(fn () => $this->record->is_active ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                ->color(fn () => $this->record->is_active ? 'warning' : 'success')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['is_active' => ! $this->record->is_active]);
                }),
            Actions\DeleteAction::make()
                ->modalHeading(fn () => '刪除 ' . $this->record->name),
        ];
    }
}
