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

// --- 數字轉中文 ---

it('converts single digits to Chinese', function () {
    $tts = new AzureTts();

    expect($tts->convertMandarinNumbers('0'))->toBe('零');
    expect($tts->convertMandarinNumbers('1'))->toBe('一');
    expect($tts->convertMandarinNumbers('9'))->toBe('九');
});

it('converts teens to Chinese', function () {
    $tts = new AzureTts();

    expect($tts->convertMandarinNumbers('10'))->toBe('十');
    expect($tts->convertMandarinNumbers('11'))->toBe('十一');
    expect($tts->convertMandarinNumbers('19'))->toBe('十九');
});

it('converts tens to Chinese', function () {
    $tts = new AzureTts();

    expect($tts->convertMandarinNumbers('20'))->toBe('二十');
    expect($tts->convertMandarinNumbers('35'))->toBe('三十五');
    expect($tts->convertMandarinNumbers('99'))->toBe('九十九');
});

it('converts hundreds to Chinese', function () {
    $tts = new AzureTts();

    expect($tts->convertMandarinNumbers('100'))->toBe('一百');
    expect($tts->convertMandarinNumbers('105'))->toBe('一百零五');
    expect($tts->convertMandarinNumbers('110'))->toBe('一百一十');
    expect($tts->convertMandarinNumbers('999'))->toBe('九百九十九');
});

it('converts thousands to Chinese', function () {
    $tts = new AzureTts();

    expect($tts->convertMandarinNumbers('1000'))->toBe('一千');
    expect($tts->convertMandarinNumbers('1001'))->toBe('一千零一');
    expect($tts->convertMandarinNumbers('1010'))->toBe('一千零一十');
    expect($tts->convertMandarinNumbers('1100'))->toBe('一千一百');
    expect($tts->convertMandarinNumbers('9999'))->toBe('九千九百九十九');
});

it('converts numbers within text context', function () {
    $tts = new AzureTts();

    expect($tts->convertMandarinNumbers('11 樓'))->toBe('十一 樓');
    expect($tts->convertMandarinNumbers('位於 3 樓的 200 坪空間'))->toBe('位於 三 樓的 二百 坪空間');
});

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
