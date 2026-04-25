<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use App\Filament\Pages\BaseEditRecord;

class EditUser extends BaseEditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('toggleActive')
                ->label(fn () => $this->record->is_active ? '停用' : '啟用')
                ->icon(fn () => $this->record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                ->color(fn () => $this->record->is_active ? 'warning' : 'success')
                ->requiresConfirmation()
                ->hidden(fn () => $this->record->id === auth()->id())
                ->action(function () {
                    $this->record->update(['is_active' => ! $this->record->is_active]);
                    Notification::make()
                        ->title($this->record->is_active ? '帳號已啟用' : '帳號已停用')
                        ->success()->send();
                    $this->fillForm();
                }),
            Actions\DeleteAction::make()
                ->hidden(fn () => $this->record->id === auth()->id()),
        ];
    }

    protected function beforeSave(): void
    {
        // 防止停用自己或移除自己的超級管理員角色
        if ($this->record->id === auth()->id()) {
            if (! ($this->data['is_active'] ?? true)) {
                Notification::make()->title('無法停用自己的帳號')->danger()->send();
                $this->halt();
            }
            if (($this->data['role'] ?? '') !== 'super_admin') {
                Notification::make()->title('無法移除自己的超級管理員角色')->danger()->send();
                $this->halt();
            }
        }
    }

    protected function afterSave(): void
    {
        $role = $this->data['role'] ?? null;
        if ($role) {
            $this->record->syncRoles([$role]);
        }

        // 一般帳號 sync 單一站台；升級超級管理員時清除站台綁定
        $siteId = $this->data['site_id_single'] ?? null;
        if ($role === 'site_admin' && $siteId) {
            $this->record->sites()->sync([$siteId]);
        } elseif ($role === 'super_admin') {
            $this->record->sites()->detach();
        }
    }
}
