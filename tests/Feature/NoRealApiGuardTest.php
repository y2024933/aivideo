<?php

declare(strict_types=1);

use App\Services\Contracts\TtsContract;
use App\Services\Contracts\VideoEditorContract;
use App\Services\Contracts\VideoGeneratorContract;
use App\Services\Stubs\StubTts;
use App\Services\Stubs\StubVideoEditor;
use App\Services\Stubs\StubVideoGenerator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Finder;

/*
 * 付費 API 與帳號風險的防護網回歸測試。
 *
 * 這組測試存在的原因是一個真實發生過的靜默失效：
 *
 *   1. 防護網原本寫在 tests/Pest.php 的 beforeEach()，但 Pest 的 beforeEach 以
 *      「宣告它的檔案」為作用域，Pest.php 不是測試檔 → hook 從來沒跑過。
 *   2. phpunit.xml 的 APP_USE_REAL_APIS=false force="true" 也沒用，因為
 *      docker compose 把 .env 注入成容器真實環境變數，$_SERVER 已有 'true'。
 *   3. 結果 config('services.use_real_apis') 在測試中是 true、TtsContract 綁的是
 *      真實 AzureTts，且 AzureTtsTest / PollKlingVideoJobTest 沒有 Storage::fake('s3')
 *      → 測試在寫 production S3 bucket。
 *
 * 失效是靜默的（測試照樣綠），所以必須有測試守著。
 */

it('測試環境強制使用 stub，不會打到付費 API', function () {
    expect(config('services.use_real_apis'))->toBeFalse();

    expect(app(VideoGeneratorContract::class))->toBeInstanceOf(StubVideoGenerator::class);
    expect(app(TtsContract::class))->toBeInstanceOf(StubTts::class);
    expect(app(VideoEditorContract::class))->toBeInstanceOf(StubVideoEditor::class);
});

it('s3 disk 一律被 fake，不會寫到 production bucket', function () {
    Storage::disk('s3')->put('guard-probe.txt', 'x');

    // fake 的 s3 是本機暫存目錄；真實 bucket 的 adapter 不會有這個路徑特徵
    expect(Storage::disk('s3')->exists('guard-probe.txt'))->toBeTrue();
    expect(Storage::disk('s3')->path('guard-probe.txt'))->toContain('framework/testing/disks/s3');
});

it('沒有任何測試檔會對外連線到真實服務', function () {
    // 這些 host 只要出現在測試檔，就必須同檔案有 Http::fake 或 Storage::fake，
    // 否則就是一條會真的打出去的請求（或是真的開瀏覽器打蝦皮 → 帳號被風控）。
    $forbidden = '#(?:creator\.)?shopee\.tw|shope\.ee|susercontent\.com|dolai\.video'
        . '|api\.klingai\.com|api\.anthropic\.com|generativelanguage\.googleapis\.com'
        . '|fal\.run|\.tts\.speech\.microsoft\.com#i';

    $offenders = [];

    foreach (Finder::create()->in(base_path('tests'))->name('*.php')->files() as $file) {
        $contents = $file->getContents();

        if (! preg_match($forbidden, $contents, $m)) {
            continue;
        }

        // 自己（規則定義處）與 fixture 不算
        if ($file->getFilename() === 'NoRealApiGuardTest.php' || str_contains($file->getRelativePathname(), 'Fixtures')) {
            continue;
        }

        if (! str_contains($contents, 'Http::fake') && ! str_contains($contents, 'Http::preventStrayRequests')) {
            $offenders[] = $file->getRelativePathname() . ' => ' . $m[0];
        }
    }

    expect($offenders)->toBeEmpty();
});

it('禁止對外連線時，任何漏網的請求都會失敗而不是真的送出', function () {
    Http::preventStrayRequests();

    expect(fn () => Http::get('https://shopee.tw/api/v4/pdp/get_pc'))
        ->toThrow(RuntimeException::class);
});

it('掃描規則本身有效：故意放一個違規字串必須被抓到', function () {
    // ⚠️ 上一條測試的對照組。沒有它的話，$forbidden 這個 regex 哪天被改壞
    // （打錯一個反斜線、少一個 alternation），$offenders 會永遠是空陣列，
    // 測試會「永遠綠」而完全失效 —— 跟它當初要防的靜默失效一模一樣。
    $forbidden = '#(?:creator\.)?shopee\.tw|shope\.ee|susercontent\.com|dolai\.video'
        . '|api\.klingai\.com|api\.anthropic\.com|generativelanguage\.googleapis\.com'
        . '|fal\.run|\.tts\.speech\.microsoft\.com#i';

    $samples = [
        "Http::get('https://shopee.tw/api/v4/pdp/get_pc');",
        "\$r = file_get_contents('https://generativelanguage.googleapis.com/v1beta/models');",
        "curl('https://api.anthropic.com/v1/messages');",
        "'https://down-tw.img.susercontent.com/file/abc'",
    ];

    foreach ($samples as $sample) {
        expect(preg_match($forbidden, $sample))->toBe(1, "規則漏掉了：{$sample}");
    }

    // 正常的測試用假網域不該被誤判
    expect(preg_match($forbidden, "Http::get('https://cdn.example.test/x.jpg');"))->toBe(0);
});
