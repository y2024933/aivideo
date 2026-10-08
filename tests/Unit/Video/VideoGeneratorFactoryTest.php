<?php

declare(strict_types=1);

use App\Enums\VideoProvider;
use App\Exceptions\UnsupportedOperationException;
use App\Models\Product;
use App\Models\Shot;
use App\Services\DolaVideoGenerator;
use App\Services\KlingVideoGenerator;
use App\Services\NullVideoGenerator;
use App\Services\Stubs\StubVideoGenerator;
use App\Services\Video\DolaAccountPool;
use App\Services\Video\VideoGeneratorFactory;

beforeEach(function () {
    $this->factory = app(VideoGeneratorFactory::class);

    // 不管開發機 .env 有沒有設，測試一律從「kling 有 key、dola 沒 profile」開始
    config([
        'services.kling.access_key' => 'test-ak',
        'services.kling.secret_key' => 'test-sk-must-be-at-least-32-bytes-long',
        'services.kling.cost_per_video' => 0.21,
        'services.browser.profiles.dola' => '',
        'services.video_provider' => 'none',
    ]);
});

// --- 防護網：use_real_apis=false 一律 stub ---

it('use_real_apis 為 false 時一律回 stub，即使明確指定 provider', function (VideoProvider $provider) {
    expect(config('services.use_real_apis'))->toBeFalse()
        ->and($this->factory->make($provider))->toBeInstanceOf(StubVideoGenerator::class);
})->with([VideoProvider::None, VideoProvider::Kling, VideoProvider::Dola, VideoProvider::Stub]);

it('stub 模式下 resolveFor 也拿到 stub，不會因為鏡頭設了 kling 就打真實 API', function () {
    $product = Product::factory()->create(['video_provider' => 'kling']);
    $shot = Shot::factory()->for($product)->create(['video_provider' => 'kling']);

    expect($this->factory->resolveFor($shot))->toBeInstanceOf(StubVideoGenerator::class);
});

// --- 真實模式下的 provider 對映 ---

it('use_real_apis 為 true 時依 provider 建出對應的 generator', function () {
    config(['services.use_real_apis' => true]);

    // 三者的建構子都只讀 config、不連外；沒有呼叫 submitImageToVideo，不會有請求送出
    expect($this->factory->make(VideoProvider::None))->toBeInstanceOf(NullVideoGenerator::class)
        ->and($this->factory->make(VideoProvider::Kling))->toBeInstanceOf(KlingVideoGenerator::class)
        ->and($this->factory->make(VideoProvider::Dola))->toBeInstanceOf(DolaVideoGenerator::class)
        ->and($this->factory->make(VideoProvider::Stub))->toBeInstanceOf(StubVideoGenerator::class);
});

it('真實模式下不指定 provider 時用全域預設', function () {
    config(['services.use_real_apis' => true, 'services.video_provider' => 'kling']);
    expect($this->factory->make())->toBeInstanceOf(KlingVideoGenerator::class);

    config(['services.video_provider' => 'none']);
    expect($this->factory->make())->toBeInstanceOf(NullVideoGenerator::class);
});

// --- default() ---

it('default() 讀 config，無法辨識或 stub 一律退回 none（最安全：不花錢）', function () {
    config(['services.video_provider' => 'kling']);
    expect($this->factory->default())->toBe(VideoProvider::Kling);

    config(['services.video_provider' => 'dola']);
    expect($this->factory->default())->toBe(VideoProvider::Dola);

    foreach (['none', 'stub', 'bogus', null] as $value) {
        config(['services.video_provider' => $value]);
        expect($this->factory->default())->toBe(VideoProvider::None);
    }
});

it('config 的預設值是 none', function () {
    expect(config('services.video_provider'))->toBe('none');
})->skip(fn () => env('VIDEO_PROVIDER') !== null, '本機 .env 覆寫了 VIDEO_PROVIDER');

// --- supports() ---

it('none 永遠可用：不需要金鑰也不需要外部服務', function () {
    expect((new NullVideoGenerator())->supports())->toBeTrue()
        ->and($this->factory->supportsProvider(VideoProvider::None))->toBeTrue();
});

it('kling 兩把金鑰都有才可用，缺任一把就不可用', function () {
    expect($this->factory->supportsProvider(VideoProvider::Kling))->toBeTrue()
        ->and((new KlingVideoGenerator())->supports())->toBeTrue();

    config(['services.kling.access_key' => null]);
    expect($this->factory->supportsProvider(VideoProvider::Kling))->toBeFalse();

    config(['services.kling.access_key' => 'test-ak', 'services.kling.secret_key' => '']);
    expect($this->factory->supportsProvider(VideoProvider::Kling))->toBeFalse();
});

it('dola 沒有 profile 就不可用', function () {
    expect($this->factory->supportsProvider(VideoProvider::Dola))->toBeFalse()
        ->and(app(DolaVideoGenerator::class)->supports())->toBeFalse();
});

it('dola 設了 profile 但瀏覽器服務沒設定齊全也不可用', function () {
    config(['services.browser.profiles.dola' => 'dola-1', 'services.browser.base_url' => null]);
    expect($this->factory->supportsProvider(VideoProvider::Dola))->toBeFalse();

    config(['services.browser.base_url' => 'http://browser:3000', 'services.browser.token' => 'tok']);
    expect($this->factory->supportsProvider(VideoProvider::Dola))->toBeTrue();
});

it('supportsProvider 不受 use_real_apis 影響 —— stub 模式下也擋得住缺 key 的 kling', function () {
    config(['services.kling.secret_key' => null]);

    expect(config('services.use_real_apis'))->toBeFalse()
        ->and($this->factory->supportsProvider(VideoProvider::Kling))->toBeFalse();
});

// --- costPerSecond() ---

it('costPerSecond：none 0、kling $0.042（$0.21／5 秒）、dola 0（免費額度）', function () {
    expect((new NullVideoGenerator())->costPerSecond())->toBe(0.0)
        ->and(round((new KlingVideoGenerator())->costPerSecond(), 4))->toBe(0.042)
        ->and(app(DolaVideoGenerator::class)->costPerSecond())->toBe(0.0)
        ->and(round($this->factory->costPerSecondOf(VideoProvider::Kling), 4))->toBe(0.042)
        ->and($this->factory->costPerSecondOf(VideoProvider::None))->toBe(0.0)
        ->and($this->factory->costPerSecondOf(VideoProvider::Dola))->toBe(0.0);
});

// --- availableOptions() ---

it('availableOptions 只列 supports() 為 true 的，且不含 stub', function () {
    expect($this->factory->availableOptions())->toBe([
        'none' => '純商品圖 Ken Burns',
        'kling' => 'Kling AI 動畫',
    ]);

    config(['services.kling.access_key' => null]);
    expect($this->factory->availableOptions())->toBe(['none' => '純商品圖 Ken Burns']);

    expect($this->factory->availableOptions())->not->toHaveKey(VideoProvider::Stub->value);
});

// --- 三層優先序 ---

it('三層優先序：鏡頭 > 商品 > config', function () {
    config(['services.video_provider' => 'dola']);

    $product = Product::factory()->create(['video_provider' => null]);
    $shot = Shot::factory()->for($product)->create(['video_provider' => null]);

    // 兩層都留空 → 吃 config
    expect($this->factory->providerFor($shot))->toBe(VideoProvider::Dola);

    // 商品有設 → 蓋掉 config
    $product->update(['video_provider' => 'none']);
    expect($this->factory->providerFor($shot->fresh()))->toBe(VideoProvider::None);

    // 鏡頭有設 → 蓋掉商品
    $shot->update(['video_provider' => 'kling']);
    expect($this->factory->providerFor($shot->fresh()))->toBe(VideoProvider::Kling);
});

it('unavailableReason：可用時 null，dola 講 NOT_IMPLEMENTED，kling 講缺金鑰', function () {
    expect($this->factory->unavailableReason(VideoProvider::None))->toBeNull()
        ->and($this->factory->unavailableReason(VideoProvider::Kling))->toBeNull()
        ->and($this->factory->unavailableReason(VideoProvider::Dola))->toContain('NOT_IMPLEMENTED');

    config(['services.kling.secret_key' => null]);
    expect($this->factory->unavailableReason(VideoProvider::Kling))->toContain('KLING_SECRET_KEY');
});

// --- hasAiMotion / estimatedCostUsd ---

it('hasAiMotion：任一鏡頭不是 none 就是 true', function () {
    $product = Product::factory()->renderable(3)->create(['video_provider' => 'none']);

    expect($this->factory->hasAiMotion($product))->toBeFalse();

    $product->shots()->orderBy('shot_order')->first()->update(['video_provider' => 'kling']);
    expect($this->factory->hasAiMotion($product->fresh()))->toBeTrue();
});

it('estimatedCostUsd 逐鏡算：只有 kling 的鏡頭要錢', function () {
    $product = Product::factory()->renderable(3)->create(['video_provider' => 'none']);
    $product->shots()->get()->each(fn (Shot $shot) => $shot->update(['duration_seconds' => 5.0]));

    expect($this->factory->estimatedCostUsd($product->fresh()))->toBe(0.0);

    $product->shots()->orderBy('shot_order')->first()->update(['video_provider' => 'kling']);
    expect(round($this->factory->estimatedCostUsd($product->fresh()), 2))->toBe(0.21);

    $product->update(['video_provider' => 'kling']);
    expect(round($this->factory->estimatedCostUsd($product->fresh()), 2))->toBe(0.63);
});

// --- none / dola 被硬呼叫時要吵，不能靜默回假的 task_id ---

it('NullVideoGenerator 被呼叫時丟 UnsupportedOperationException 並說清楚為什麼', function () {
    $generator = new NullVideoGenerator();

    expect(fn () => $generator->submitImageToVideo('https://example.com/a.jpg', 'push in'))
        ->toThrow(UnsupportedOperationException::class, 'Ken Burns');

    expect(fn () => $generator->queryTaskStatus('task_1'))
        ->toThrow(UnsupportedOperationException::class);
});

it('DolaVideoGenerator 被呼叫時丟 NOT_IMPLEMENTED，並建議改用 Kling 或 Ken Burns', function () {
    $generator = app(DolaVideoGenerator::class);

    expect(fn () => $generator->submitImageToVideo('https://example.com/a.jpg', 'push in'))
        ->toThrow(RuntimeException::class, 'NOT_IMPLEMENTED');

    expect(fn () => $generator->queryTaskStatus('task_1'))
        ->toThrow(RuntimeException::class, 'Kling');
});

it('name() 回報自己是哪個 provider', function () {
    expect((new NullVideoGenerator())->name())->toBe(VideoProvider::None)
        ->and((new KlingVideoGenerator())->name())->toBe(VideoProvider::Kling)
        ->and(app(DolaVideoGenerator::class)->name())->toBe(VideoProvider::Dola)
        ->and((new StubVideoGenerator())->name())->toBe(VideoProvider::Stub);
});

// --- DolaAccountPool：介面預留，不實作 ---

it('DolaAccountPool 從 config 讀逗號分隔的 profile，沒設定時 acquire 會吵', function () {
    $pool = app(DolaAccountPool::class);

    expect($pool->status())->toMatchArray(['profiles' => [], 'count' => 0, 'implemented' => false])
        ->and(fn () => $pool->acquire())->toThrow(RuntimeException::class, 'BROWSER_PROFILES_DOLA');

    config(['services.browser.profiles.dola' => ' dola-1 , dola-2 ,']);

    expect($pool->status()['profiles'])->toBe(['dola-1', 'dola-2'])
        ->and($pool->status()['count'])->toBe(2)
        // 第一版固定回 profiles[0]，刻意不輪替（多帳號輪替刷額度是 ToS 違規）
        ->and($pool->acquire())->toBe('dola-1')
        ->and($pool->release('dola-1', true))->toBeNull();
});
