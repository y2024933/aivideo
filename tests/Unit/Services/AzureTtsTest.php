<?php

declare(strict_types=1);

use App\Services\AzureTts;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'services.azure_tts.key' => 'test-azure-key-123',
        'services.azure_tts.region' => 'eastasia',
    ]);
});

// --- Config 驗證 ---

it('throws RuntimeException when key is missing', function () {
    config(['services.azure_tts.key' => null]);
    new AzureTts();
})->throws(RuntimeException::class, 'AZURE_TTS_KEY is not configured');

it('throws RuntimeException when region is missing', function () {
    config(['services.azure_tts.region' => null]);
    new AzureTts();
})->throws(RuntimeException::class, 'AZURE_TTS_REGION is not configured');

// --- SSML 格式與認證 Header ---

it('sends correct SSML body and authentication header', function () {
    Storage::fake('local');
    Http::fake([
        'eastasia.tts.speech.microsoft.com/*' => Http::response('fake-mp3-binary', 200),
    ]);

    $tts = new AzureTts();
    $tts->synthesize('你好世界');

    Http::assertSent(function ($request) {
        $body = $request->body();
        $headers = $request->headers();

        // 驗證認證 header
        expect($headers['Ocp-Apim-Subscription-Key'][0])->toBe('test-azure-key-123');

        // 驗證 Content-Type
        expect($headers['Content-Type'][0])->toBe('application/ssml+xml');

        // 驗證 Output format
        expect($headers['X-Microsoft-OutputFormat'][0])->toBe('audio-24khz-48kbitrate-mono-mp3');

        // 驗證 SSML 結構
        expect($body)->toContain("<speak version='1.0' xml:lang='zh-TW'>");
        expect($body)->toContain("name='zh-TW-HsiaoChenNeural'");
        expect($body)->toContain('你好世界');

        return true;
    });
});

it('uses custom voice name in SSML', function () {
    Storage::fake('local');
    Http::fake([
        'eastasia.tts.speech.microsoft.com/*' => Http::response('fake-mp3', 200),
    ]);

    $tts = new AzureTts();
    $tts->synthesize('測試', 'zh-TW-YunJheNeural');

    Http::assertSent(fn ($request) => str_contains($request->body(), "name='zh-TW-YunJheNeural'"));
});

it('returns audio_url and duration_seconds on success', function () {
    Storage::fake('local');
    Http::fake([
        'eastasia.tts.speech.microsoft.com/*' => Http::response('fake-mp3-data', 200),
    ]);

    $tts = new AzureTts();
    $result = $tts->synthesize('你好世界');

    expect($result)->toHaveKeys(['audio_url', 'duration_seconds']);
    expect($result['audio_url'])->toContain('/storage/voiceovers/');
    expect($result['audio_url'])->toEndWith('.mp3');
    expect($result['duration_seconds'])->toBeGreaterThan(0);
});

it('saves mp3 file to storage', function () {
    Storage::fake('public');
    Http::fake([
        'eastasia.tts.speech.microsoft.com/*' => Http::response('binary-audio-content', 200),
    ]);

    $tts = new AzureTts();
    $result = $tts->synthesize('儲存測試');

    $filename = basename($result['audio_url']);
    Storage::disk('public')->assertExists("voiceovers/{$filename}");
});

it('throws RuntimeException when API returns error', function () {
    Http::fake([
        'eastasia.tts.speech.microsoft.com/*' => Http::response('Unauthorized', 401),
    ]);

    $tts = new AzureTts();
    $tts->synthesize('失敗測試');
})->throws(RuntimeException::class, 'Azure TTS failed: HTTP 401');

// --- 數字前處理（轉換邏輯本身的測試在 tests/Unit/Support/MandarinNumberTest.php）---

it('preprocesses numbers before sending to Azure', function () {
    Storage::fake('local');
    Http::fake([
        'eastasia.tts.speech.microsoft.com/*' => Http::response('fake', 200),
    ]);

    $tts = new AzureTts();
    $tts->synthesize('11 樓的豪宅');

    Http::assertSent(function ($request) {
        $body = $request->body();
        // SSML 裡應該是中文數字
        expect($body)->toContain('十一');
        expect($body)->not->toContain('11');

        return true;
    });
});
