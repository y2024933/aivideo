<?php

declare(strict_types=1);

namespace Tests;

use App\Services\Contracts\TtsContract;
use App\Services\Contracts\VideoEditorContract;
use App\Services\Contracts\VideoGeneratorContract;
use App\Services\Stubs\StubTts;
use App\Services\Stubs\StubVideoEditor;
use App\Services\Stubs\StubVideoGenerator;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * 付費 API 防護網。
     *
     * ⚠️ 這段原本寫在 tests/Pest.php 的 beforeEach()，但 Pest 的 beforeEach 是以
     * 「宣告它的檔案」為作用域（Pest\PendingCalls\BeforeEachCall 用 $filename 當 key），
     * Pest.php 本身不是測試檔，所以那個 hook 從來沒執行過。放在 setUp() 才真的生效。
     *
     * phpunit.xml 的 APP_USE_REAL_APIS=false 也不可靠：docker compose 會把 .env
     * 注入成容器的真實環境變數，$_SERVER 已有值時 PHPUnit 的 force 覆寫不到 Laravel
     * 讀取的來源，因此這裡直接改 config。
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.use_real_apis' => false,
            // ⚠️ phpunit.xml:27 的 QUEUE_CONNECTION=sync 擋不住 —— docker compose 把
            //    .env.docker 的 QUEUE_CONNECTION=redis 注入成容器真實環境變數，
            //    $_SERVER 已有值時 Laravel 讀到的是 redis，dispatch() 會把 job 丟進
            //    Redis 而不是同步執行，job 永遠不跑而測試只會看到「狀態沒變」。
            //    跟 use_real_apis 同一個陷阱，一樣只能在這裡直接改 config。
            'queue.default' => 'sync',
        ]);

        // S3 一律 fake：AWS 憑證在測試環境同樣有效，不攔住就會真的寫進 production bucket
        Storage::fake('s3');

        $this->app->bind(VideoGeneratorContract::class, fn () => new StubVideoGenerator());
        $this->app->bind(TtsContract::class, fn () => new StubTts());
        $this->app->bind(VideoEditorContract::class, fn () => new StubVideoEditor());
    }
}
