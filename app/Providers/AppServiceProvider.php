<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Contracts\ImageGeneratorContract;
use App\Services\Contracts\TtsContract;
use App\Services\Contracts\VideoEditorContract;
use App\Services\Contracts\VideoGeneratorContract;
use App\Services\Stubs\StubImageGenerator;
use App\Services\Stubs\StubTts;
use App\Services\Stubs\StubVideoEditor;
use App\Services\Stubs\StubVideoGenerator;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ImageGeneratorContract::class, fn () =>
            config('services.use_real_apis')
                ? $this->app->make(\App\Services\FalKontextImageGenerator::class)
                : new StubImageGenerator()
        );

        $this->app->bind(VideoGeneratorContract::class, fn () =>
            config('services.use_real_apis')
                ? $this->app->make(\App\Services\KlingVideoGenerator::class)
                : new StubVideoGenerator()
        );

        $this->app->bind(TtsContract::class, fn () =>
            config('services.use_real_apis')
                ? $this->app->make(\App\Services\AzureTts::class)
                : new StubTts()
        );

        $this->app->bind(VideoEditorContract::class, fn () =>
            config('services.use_real_apis')
                ? $this->app->make(\App\Services\RemotionVideoEditor::class)
                : new StubVideoEditor()
        );
    }

    public function boot(): void
    {
        //
    }
}
