<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Contracts\BrowserAutomationContract;
use App\Services\Contracts\ScriptWriterContract;
use App\Services\Contracts\TtsContract;
use App\Services\Contracts\VideoEditorContract;
use App\Services\Contracts\VideoGeneratorContract;
use App\Services\Llm\ScriptWriterFactory;
use App\Services\Stubs\StubBrowserAutomation;
use App\Services\Stubs\StubTts;
use App\Services\Stubs\StubVideoEditor;
use App\Services\Stubs\StubVideoGenerator;
use App\Services\Video\VideoGeneratorFactory;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * 動畫 generator 的「全域預設」。per-shot 的解析請用
         * VideoGeneratorFactory::resolveFor($shot)，不要靠這顆綁定 ——
         * 它只知道 config 的全域預設，不知道商品／鏡頭的覆寫。
         *
         * ⚠️ use_real_apis = false 的分支**必須**直接 new 而不是經 factory：
         *    factory 的 stub 路徑會回頭解析這個 contract（讓測試能用
         *    app()->instance() 換成 mock），兩邊都繞一圈就會無限遞迴。
         */
        $this->app->bind(VideoGeneratorContract::class, fn () =>
            config('services.use_real_apis')
                ? $this->app->make(VideoGeneratorFactory::class)->make()
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

        /*
         * ⚠️ 這顆綁定比其他三顆更要小心：綁錯會真的開瀏覽器打蝦皮，而蝦皮封的是帳號，
         *    帳號只能人工 SMS OTP 恢復。所以同樣掛在 use_real_apis 下 ——
         *    測試環境（Tests\TestCase 強制 use_real_apis = false）永遠拿到 stub。
         */
        $this->app->bind(BrowserAutomationContract::class, fn () =>
            config('services.use_real_apis')
                ? $this->app->make(\App\Services\Browser\PlaywrightBrowserAutomation::class)
                : new StubBrowserAutomation()
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
