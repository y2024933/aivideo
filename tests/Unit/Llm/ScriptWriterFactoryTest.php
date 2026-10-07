<?php

declare(strict_types=1);

use App\Enums\ScriptProvider;
use App\Services\ClaudeScriptWriter;
use App\Services\Contracts\ScriptWriterContract;
use App\Services\GeminiScriptWriter;
use App\Services\Llm\ScriptWriterFactory;
use App\Services\Stubs\StubScriptWriter;

beforeEach(function () {
    $this->factory = app(ScriptWriterFactory::class);
});

// --- 防護網：use_real_apis=false 一律 stub ---

it('use_real_apis 為 false 時一律回 stub，即使明確指定 provider', function (ScriptProvider $provider) {
    // key 都設好，證明回 stub 不是因為缺 key
    config([
        'services.use_real_apis' => false,
        'services.gemini.api_key' => 'g',
        'services.anthropic.api_key' => 'a',
    ]);

    expect($this->factory->make($provider))->toBeInstanceOf(StubScriptWriter::class);
})->with([ScriptProvider::Gemini, ScriptProvider::Claude, ScriptProvider::Stub]);

it('容器解析 ScriptWriterContract 在測試環境拿到 StubScriptWriter', function () {
    expect(config('services.use_real_apis'))->toBeFalse()
        ->and(app(ScriptWriterContract::class))->toBeInstanceOf(StubScriptWriter::class);
});

// --- 真實模式下的 provider 對映 ---

it('use_real_apis 為 true 時依 provider 建出對應的 writer', function () {
    config([
        'services.use_real_apis' => true,
        'services.gemini.api_key' => 'test-gemini-key',
        'services.anthropic.api_key' => 'test-anthropic-key',
    ]);

    // 兩者的建構子都只讀 config、不連外；write() 沒被呼叫，不會有任何請求送出
    expect($this->factory->make(ScriptProvider::Gemini))->toBeInstanceOf(GeminiScriptWriter::class)
        ->and($this->factory->make(ScriptProvider::Claude))->toBeInstanceOf(ClaudeScriptWriter::class)
        ->and($this->factory->make(ScriptProvider::Stub))->toBeInstanceOf(StubScriptWriter::class);
});

it('真實模式下不指定 provider 時用全域預設', function () {
    config(['services.use_real_apis' => true, 'services.gemini.api_key' => 'g', 'services.script_provider' => 'gemini']);

    expect($this->factory->make())->toBeInstanceOf(GeminiScriptWriter::class);

    config(['services.script_provider' => 'claude', 'services.anthropic.api_key' => 'a']);

    expect($this->factory->make())->toBeInstanceOf(ClaudeScriptWriter::class);
});

it('缺 key 時由 writer 的建構子丟例外（而不是靜默換 provider）', function () {
    config(['services.use_real_apis' => true, 'services.gemini.api_key' => null]);

    expect(fn () => $this->factory->make(ScriptProvider::Gemini))
        ->toThrow(RuntimeException::class, 'GEMINI_API_KEY 未設定');
});

// --- default() ---

it('default() 讀 config，無法辨識的值退回 Gemini', function () {
    config(['services.script_provider' => 'claude']);
    expect($this->factory->default())->toBe(ScriptProvider::Claude);

    config(['services.script_provider' => 'gemini']);
    expect($this->factory->default())->toBe(ScriptProvider::Gemini);

    config(['services.script_provider' => 'bogus']);
    expect($this->factory->default())->toBe(ScriptProvider::Gemini);

    config(['services.script_provider' => null]);
    expect($this->factory->default())->toBe(ScriptProvider::Gemini);
});

it('config 的預設值是 gemini', function () {
    expect(config('services.script_provider'))->toBe('gemini')
        ->and(config('services.gemini.model'))->toBe('gemini-3.5-flash-lite');
});

// --- availableOptions() ---

it('availableOptions 只列出 key 有設的 provider', function () {
    config(['services.gemini.api_key' => 'g', 'services.anthropic.api_key' => null]);
    expect($this->factory->availableOptions())->toBe(['gemini' => 'Gemini（免費額度）']);

    config(['services.gemini.api_key' => null, 'services.anthropic.api_key' => 'a']);
    expect($this->factory->availableOptions())->toBe(['claude' => 'Claude Opus 5（付費，品質較佳）']);

    config(['services.gemini.api_key' => 'g', 'services.anthropic.api_key' => 'a']);
    expect(array_keys($this->factory->availableOptions()))->toBe(['gemini', 'claude']);

    config(['services.gemini.api_key' => '', 'services.anthropic.api_key' => '']);
    expect($this->factory->availableOptions())->toBe([]);
});

it('availableOptions 不列 stub —— 它不是 operator 該選的東西', function () {
    config(['services.gemini.api_key' => 'g', 'services.anthropic.api_key' => 'a']);

    expect($this->factory->availableOptions())->not->toHaveKey(ScriptProvider::Stub->value);
});
