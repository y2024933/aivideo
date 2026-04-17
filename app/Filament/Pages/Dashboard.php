<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        return true;
    }

    public function getWidgets(): array
    {
        if (auth()->user()?->isSuperAdmin()) {
            return [
                \Filament\Widgets\AccountWidget::class,
                \Filament\Widgets\FilamentInfoWidget::class,
            ];
        }

        return [
            \Filament\Widgets\AccountWidget::class,
        ];
    }
}
