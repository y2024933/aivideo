<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Filament\Support\Enums\Alignment;

class EditProfile extends BaseEditProfile
{
    public ?string $previousUrl = null;

    public function mount(): void
    {
        $this->previousUrl = url()->previous();
        parent::mount();
    }

    protected function getPasswordFormComponent(): TextInput
    {
        return parent::getPasswordFormComponent()
            ->minLength(6)
            ->helperText('留空則不修改密碼，最少 6 位');
    }

    public function getFormActionsAlignment(): Alignment
    {
        return Alignment::Center;
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->previousUrl ?? filament()->getUrl();
    }
}
