<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Contracts\ScriptWriterContract;
use App\Services\Contracts\TtsContract;
use App\Services\Contracts\VideoEditorContract;
use App\Services\Contracts\VideoGeneratorContract;
use App\Services\Llm\ScriptWriterFactory;
use App\Services\Stubs\StubTts;
use App\Services\Stubs\StubVideoEditor;
use App\Services\Stubs\StubVideoGenerator;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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

        // 寫稿是唯一可插拔多 provider 的服務（Gemini／Claude），stub 的判斷在 factory 裡
        $this->app->bind(ScriptWriterContract::class, fn () =>
            $this->app->make(ScriptWriterFactory::class)->make()
        );
    }

    public function boot(): void
    {
        //
    }
}
