<?php

namespace App\Providers;

use Filament\Actions\Action;
use Filament\Pages\BasePage;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Filament 後台 Edit/Create 頁面儲存/取消按鈕置中
        BasePage::formActionsAlignment(Alignment::Center);

        // 儲存按鈕改為綠色
        Action::configureUsing(function (Action $action): void {
            if ($action->getName() === 'save') {
                $action->color('success');
            }
        });
    }
}
