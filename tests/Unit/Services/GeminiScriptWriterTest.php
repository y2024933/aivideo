<?php

declare(strict_types=1);

use App\Data\Llm\ScriptOutput;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\GeminiScriptWriter;
use Illuminate\Support\Facades\Http;

/*
 * ⚠️ 全程 Http::fake()：這個檔案絕對不可以打到真實的 Gemini endpoint。
 * Http::preventStrayRequests() 在 beforeEach 開著，漏網的請求會直接丟例外而不是送出去。
 */

beforeEach(function () {
    config([
        'services.gemini.api_key' => 'test-gemini-key',
        'services.gemini.model' => 'gemini-flash-latest',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        'services.gemini.cost_per_mtok_input' => 0.0,
        'services.gemini.cost_per_mtok_output' => 0.0,
    ]);

    Http::preventStrayRequests();

    $this->product = Product::factory()->status(ProductStatus::ProductApproved)->create();
    ProductImage::factory()->for($this->product)->create(['sort_order' => 1, 'is_primary' => true]);
});

/** Gemini 的 candidates[0].content.parts[0].text 是一段 JSON 字串 */
function geminiResponse(array $script = [], array $overrides = []): array
{
    $script = $script ?: [
        'shots' => [
            ['role' => 'hook', 'imageRef' => 0, 'kenBurns' => 'zoomIn', 'durationSeconds' => 2.0, 'subtitle' => '出門才發現耳機沒電', 'voiceoverText' => '', 'transition' => 'cut'],
            ['role' => 'pain', 'imageRef' => 0, 'kenBurns' => 'panRight', 'durationSeconds' => 3.0, 'subtitle' => '整路只剩引擎聲', 'voiceoverText' => '', 'transition' => 'crossfade'],
        ],
        'caption' => '通勤路上想要一點安靜。',
        'hashtags' => ['藍牙耳機', '通勤好物', '耳機推薦'],
    ];

    return array_replace_recursive([
        'candidates' => [[
            'content' => ['role' => 'model', 'parts' => [['text' => json_encode($script, JSON_UNESCAPED_UNICODE)]]],
            'finishReason' => 'STOP',
        ]],
        'usageMetadata' => ['promptTokenCount' => 5200, 'candidatesTokenCount' => 640, 'thoughtsTokenCount' => 120, 'cachedContentTokenCount' => 4800, 'totalTokenCount' => 5960],
        'modelVersion' => 'gemini-3.8-flash',
    ], $overrides);
}

function fakeGemini(array $body, int $status = 200): void
{
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response($body, $status)]);
}

// --- 正常路徑 ---

it('把 Gemini 回傳的 JSON parse 成 ScriptOutput', function () {
    fakeGemini(geminiResponse());

    $output = app(GeminiScriptWriter::class)->write($this->product);

    expect($output)->toBeInstanceOf(ScriptOutput::class)
        ->and($output->shots)->toHaveCount(2)
        ->and($output->shots[0]->role)->toBe('hook')
        ->and($output->shots[0]->subtitle)->toBe('出門才發現耳機沒電')
        ->and($output->shots[1]->transition)->toBe('crossfade')
        ->and($output->caption)->toBe('通勤路上想要一點安靜。')
        ->and($output->hashtags)->toBe(['藍牙耳機', '通勤好物', '耳機推薦']);
});

it('打的是 models/{model}:generateContent，且帶 systemInstruction 與 responseSchema', function () {
    fakeGemini(geminiResponse());

    app(GeminiScriptWriter::class)->write($this->product);

    Http::assertSent(function ($request) {
        $body = $request->data();
        $shotProperties = $body['generationConfig']['responseSchema']['properties']['shots']['items']['properties'];

        expect($request->url())->toBe('https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent')
            ->and($request->method())->toBe('POST')
            // 三個 system block 串成一段 text，cache_control 被忽略
            ->and($body['systemInstruction']['parts'])->toHaveCount(1)
            ->and($body['systemInstruction']['parts'][0]['text'])->toContain('你是台灣蝦皮分潤')
            ->and($body['systemInstruction']['parts'][0]['text'])->toContain('廣告法規紅線')
            ->and(json_encode($body['systemInstruction']))->not->toContain('cache_control')
            ->and($body['contents'][0]['role'])->toBe('user')
            ->and($body['contents'][0]['parts'][0]['text'])->toContain('# 商品資料')
            ->and($body['generationConfig']['responseMimeType'])->toBe('application/json')
            ->and($shotProperties)->toHaveCount(7)
            ->and($body['generationConfig']['maxOutputTokens'])->toBe(8000);

        return true;
    });
});

it('API key 放 x-goog-api-key header，不出現在 URL 裡', function () {
    fakeGemini(geminiResponse());

    app(GeminiScriptWriter::class)->write($this->product);

    Http::assertSent(function ($request) {
        expect($request->header('x-goog-api-key'))->toBe(['test-gemini-key'])
            // key 進 URL 就等於進 log 與例外堆疊
            ->and($request->url())->not->toContain('test-gemini-key')
            ->and($request->url())->not->toContain('key=');

        return true;
    });
});

it('重試時把違規回饋帶進 user prompt', function () {
    fakeGemini(geminiResponse());

    app(GeminiScriptWriter::class)->write($this->product, ['retry_feedback' => '「这」是簡體字，請改成「這」']);

    Http::assertSent(fn ($request) => str_contains($request->data()['contents'][0]['parts'][0]['text'], '是簡體字'));
});

// --- lastUsage ---

it('lastUsage 從 usageMetadata 取 token 數，thinking token 併入 output', function () {
    fakeGemini(geminiResponse());

    $writer = app(GeminiScriptWriter::class);
    $writer->write($this->product);

    expect($writer->lastUsage())->toBe([
        'input' => 5200,
        'output' => 760,      // candidatesTokenCount 640 + thoughtsTokenCount 120
        'cache_read' => 4800,
        'cache_write' => 0,   // implicit cache 不收建立費
        'cost_usd' => 0.0,    // 免費 tier 單價為 0
        'model' => 'gemini-3.8-flash',
    ]);
});

it('升付費後單價不為 0 時算得出成本', function () {
    config(['services.gemini.cost_per_mtok_input' => 0.75, 'services.gemini.cost_per_mtok_output' => 3.75]);
    fakeGemini(geminiResponse());

    $writer = app(GeminiScriptWriter::class);
    $writer->write($this->product);

    expect($writer->lastUsage()['cost_usd'])->toBe(round(5200 / 1e6 * 0.75 + 760 / 1e6 * 3.75, 6));
});

it('write() 之前 lastUsage 是全 0', function () {
    expect(app(GeminiScriptWriter::class)->lastUsage())
        ->toBe(['input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0, 'cost_usd' => 0.0, 'model' => '']);
});

// --- 錯誤處理 ---

it('缺 GEMINI_API_KEY 時建構就丟例外，不會送出請求', function () {
    fakeGemini(geminiResponse());
    config(['services.gemini.api_key' => null]);

    expect(fn () => app(GeminiScriptWriter::class))->toThrow(RuntimeException::class, 'GEMINI_API_KEY 未設定');

    Http::assertNothingSent();
});

it('429 會逐一嘗試備援 model，全部用盡後說明是額度問題並指向 Claude', function () {
    config(['services.gemini.fallback_models' => 'fallback-a,fallback-b']);
    fakeGemini(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded']], 429);

    expect(fn () => app(GeminiScriptWriter::class)->write($this->product))
        ->toThrow(RuntimeException::class, '額度');

    expect(fn () => app(GeminiScriptWriter::class)->write($this->product))
        ->toThrow(RuntimeException::class, 'Claude');
});

it('主要 model 503 時會自動改用備援 model', function () {
    config(['services.gemini.model' => 'busy-model', 'services.gemini.fallback_models' => 'spare-model']);

    Http::fake([
        '*busy-model:generateContent' => Http::response(['error' => ['code' => 503, 'message' => 'high demand']], 503),
        '*spare-model:generateContent' => Http::response(geminiResponse()),
    ]);

    $output = app(GeminiScriptWriter::class)->write($this->product);

    expect($output->shots)->not->toBeEmpty()
        ->and(app(GeminiScriptWriter::class)->lastUsage())->toBeArray();
});

it('已下架的 model 回 404 時會換下一個，不是直接失敗', function () {
    config(['services.gemini.model' => 'retired-model', 'services.gemini.fallback_models' => 'spare-model']);

    Http::fake([
        '*retired-model:generateContent' => Http::response(
            ['error' => ['code' => 404, 'message' => 'no longer available to new users']], 404),
        '*spare-model:generateContent' => Http::response(geminiResponse()),
    ]);

    expect(app(GeminiScriptWriter::class)->write($this->product)->shots)->not->toBeEmpty();
});

it('finishReason 為 SAFETY 時訊息說明是安全機制', function (string $reason) {
    fakeGemini(geminiResponse(overrides: ['candidates' => [['finishReason' => $reason]]]));

    expect(fn () => app(GeminiScriptWriter::class)->write($this->product))
        ->toThrow(RuntimeException::class, '安全');
})->with(['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST']);

it('promptFeedback.blockReason 也走安全機制的訊息', function () {
    fakeGemini(['promptFeedback' => ['blockReason' => 'SAFETY']]);

    expect(fn () => app(GeminiScriptWriter::class)->write($this->product))
        ->toThrow(RuntimeException::class, '安全');
});

it('finishReason 為 MAX_TOKENS 時訊息說明輸出被截斷', function () {
    fakeGemini(geminiResponse(overrides: ['candidates' => [['finishReason' => 'MAX_TOKENS']]]));

    expect(fn () => app(GeminiScriptWriter::class)->write($this->product))
        ->toThrow(RuntimeException::class, '截斷');
});

it('回傳不是 JSON 時丟明確例外，不讓 null 往下傳', function () {
    fakeGemini(geminiResponse(overrides: ['candidates' => [['content' => ['parts' => [['text' => '好的，這是你的腳本：']]]]]]));

    expect(fn () => app(GeminiScriptWriter::class)->write($this->product))
        ->toThrow(RuntimeException::class, '不是合法 JSON');
});

it('沒有任何 candidate 時丟明確例外', function () {
    fakeGemini(['usageMetadata' => ['promptTokenCount' => 10], 'candidates' => []]);

    expect(fn () => app(GeminiScriptWriter::class)->write($this->product))
        ->toThrow(RuntimeException::class, '沒有回傳任何內容');
});

it('JSON 合法但沒有鏡頭時丟 ScriptSchema 的例外', function () {
    fakeGemini(geminiResponse(['caption' => 'x', 'hashtags' => ['a']]));

    expect(fn () => app(GeminiScriptWriter::class)->write($this->product))
        ->toThrow(RuntimeException::class, '沒有任何鏡頭');
});

it('其他 HTTP 錯誤帶上狀態碼與 error.message', function () {
    fakeGemini(['error' => ['code' => 400, 'message' => 'Invalid JSON payload']], 400);

    expect(fn () => app(GeminiScriptWriter::class)->write($this->product))
        ->toThrow(RuntimeException::class, 'HTTP 400');

    expect(fn () => app(GeminiScriptWriter::class)->write($this->product))
        ->toThrow(RuntimeException::class, 'Invalid JSON payload');
});
