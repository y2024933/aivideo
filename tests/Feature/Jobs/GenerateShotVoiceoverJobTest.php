<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Jobs\GenerateShotVoiceoverJob;
use App\Models\Product;
use App\Models\Shot;
use App\Services\Contracts\TtsContract;
use App\Services\Llm\ShotDurationEstimator;
use App\Services\Stubs\StubTts;

/**
 * 單一鏡頭配音 Job 的直接測試。
 *
 * AssetsPipelineTest 走的是「整條 ② → ③ → 渲染」的接力，這裡只盯這顆 Job 自己的合約：
 *   - 成功時寫滿哪些欄位（少一個 Remotion 就播不出聲）
 *   - 失敗的三種型態（TTS 丟例外 / 沒有 S3 網址 / 空稿）
 *   - 入口守衛：只有 voiceover_status = pending 才動，所以重跑不會重複付錢
 *
 * ⚠️ TTS 一律是 stub（Tests\TestCase::setUp() 綁的），不會碰 Azure。
 */

/** status = assets_generating、audio_mode = tts、N 鏡待配音的商品 */
function voiceoverProduct(int $shots = 1, array $attributes = [], array $shotAttributes = []): Product
{
    // autopilot 關掉，測試才停在 ③ 而不是一路渲染下去（渲染行為由 AssetsPipelineTest 負責）
    config(['video.autopilot.assets' => false]);

    $product = Product::factory()->withTts()->status(ProductStatus::AssetsGenerating)->create($attributes);

    foreach (range(1, $shots) as $i) {
        Shot::factory()->for($product)->create([
            'shot_id' => sprintf('S%02d', $i),
            'shot_order' => $i,
            'voiceover_text' => "第 {$i} 鏡的配音稿，大約這麼長。",
            'voiceover_status' => 'pending',
            ...$shotAttributes,
        ]);
    }

    return $product;
}

/** 記錄每次呼叫的 TTS 替身；$result 可改成失敗型態 */
function spyTts(?array $result = null, ?Throwable $throws = null): object
{
    $spy = new class implements TtsContract
    {
        public array $calls = [];

        public ?array $result = null;

        public ?Throwable $throws = null;

        public function synthesize(string $text, string $voiceName = 'zh-TW-HsiaoChenNeural'): array
        {
            $this->calls[] = ['text' => $text, 'voice' => $voiceName];

            if ($this->throws !== null) {
                throw $this->throws;
            }

            return $this->result ?? (new StubTts())->synthesize($text, $voiceName);
        }
    };

    $spy->result = $result;
    $spy->throws = $throws;
    app()->instance(TtsContract::class, $spy);

    return $spy;
}

// ─────────────────────────────────────────────────────────────
// 成功路徑
// ─────────────────────────────────────────────────────────────

it('fills every column Remotion needs and recomputes the shot duration', function () {
    $product = voiceoverProduct();
    $shot = $product->shots()->sole();
    $spy = spyTts(['audio_url' => '/storage/voiceovers/a.mp3', 'remote_url' => 'https://s3.test/voiceovers/a.mp3', 'duration_seconds' => 4.2]);

    dispatch_sync(new GenerateShotVoiceoverJob($shot->id));
    $shot->refresh();

    expect($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['text'])->toBe($shot->voiceover_text)
        ->and($shot->voiceover_status)->toBe('done')
        ->and($shot->voiceover_url)->toBe('/storage/voiceovers/a.mp3')
        // ⚠️ remote_url 是 Lambda 唯一讀得到的來源，localPath 只給後台預覽
        ->and($shot->voiceover_remote_url)->toBe('https://s3.test/voiceovers/a.mp3')
        ->and($shot->voiceover_voice_id)->toBe('zh-TW-HsiaoChenNeural')
        ->and((float) $shot->voiceover_duration_sec)->toBe(4.2)
        // 鏡頭秒數必須被配音長度推翻（4.2 + 0.6 padding → 5.0），否則配音會被下一鏡切掉
        ->and((float) $shot->duration_seconds)->toBe(ShotDurationEstimator::fromTts(4.2, (string) $shot->subtitle, 'crossfade'))
        ->and((float) $shot->duration_seconds)->toBe(5.0)
        ->and((float) $shot->duration_seconds)->toBeGreaterThan(4.2);
});

it('writes a done voiceover row linked to the shot', function () {
    $product = voiceoverProduct();
    $shot = $product->shots()->sole();
    spyTts(['audio_url' => '/storage/a.mp3', 'remote_url' => 'https://s3.test/a.mp3', 'duration_seconds' => 3.0]);

    dispatch_sync(new GenerateShotVoiceoverJob($shot->id));

    $row = $product->voiceovers()->sole();

    expect($row->shot_uuid)->toBe($shot->id)
        ->and($row->text)->toBe($shot->voiceover_text)
        ->and($row->voice_id)->toBe('zh-TW-HsiaoChenNeural')
        ->and($row->remote_url)->toBe('https://s3.test/a.mp3')
        ->and((float) $row->duration_seconds)->toBe(3.0)
        ->and($row->status)->toBe('done')
        ->and($row->error_message)->toBeNull()
        ->and($row->shot->is($shot))->toBeTrue();
});

it('settles the pipeline only after the last shot finishes', function () {
    $product = voiceoverProduct(shots: 3);
    spyTts();
    $shots = $product->shots()->orderBy('shot_order')->get();

    dispatch_sync(new GenerateShotVoiceoverJob($shots[0]->id));
    dispatch_sync(new GenerateShotVoiceoverJob($shots[1]->id));

    // 還有一鏡 pending → 不可以提早推進（舊版的經典 bug）
    expect($product->refresh()->status)->toBe(ProductStatus::AssetsGenerating);

    dispatch_sync(new GenerateShotVoiceoverJob($shots[2]->id));

    expect($product->refresh()->status)->toBe(ProductStatus::AssetsPendingReview);
});

it('uses the product preferred voice, falling back to the taiwanese default', function () {
    $spy = spyTts();
    $custom = voiceoverProduct(attributes: ['voice_id_preferred' => 'zh-TW-YunJheNeural']);
    dispatch_sync(new GenerateShotVoiceoverJob($custom->shots()->sole()->id));

    $blank = voiceoverProduct(attributes: ['voice_id_preferred' => null]);
    dispatch_sync(new GenerateShotVoiceoverJob($blank->shots()->sole()->id));

    expect(array_column($spy->calls, 'voice'))->toBe(['zh-TW-YunJheNeural', 'zh-TW-HsiaoChenNeural'])
        ->and($custom->shots()->sole()->voiceover_voice_id)->toBe('zh-TW-YunJheNeural')
        ->and($blank->shots()->sole()->voiceover_voice_id)->toBe('zh-TW-HsiaoChenNeural');
});

it('uses a tighter padding when the shot cuts instead of crossfading', function () {
    $cut = voiceoverProduct(shotAttributes: ['transition' => 'cut', 'subtitle' => '短']);
    $fade = voiceoverProduct(shotAttributes: ['transition' => 'crossfade', 'subtitle' => '短']);
    spyTts(['audio_url' => '/a.mp3', 'remote_url' => 'https://s3.test/a.mp3', 'duration_seconds' => 3.2]);

    dispatch_sync(new GenerateShotVoiceoverJob($cut->shots()->sole()->id));
    dispatch_sync(new GenerateShotVoiceoverJob($fade->shots()->sole()->id));

    // cut 不產生轉場重疊 → 3.2 + 0.3 = 3.5；crossfade 要留 0.6 > overlap 0.5 → 4.0
    expect((float) $cut->shots()->sole()->duration_seconds)->toBe(3.5)
        ->and((float) $fade->shots()->sole()->duration_seconds)->toBe(4.0);
});

it('inherits the product global transition when the shot has none', function () {
    $product = voiceoverProduct(attributes: ['global_transition' => 'cut'], shotAttributes: ['transition' => null, 'subtitle' => '短']);
    spyTts(['audio_url' => '/a.mp3', 'remote_url' => 'https://s3.test/a.mp3', 'duration_seconds' => 3.2]);

    dispatch_sync(new GenerateShotVoiceoverJob($product->shots()->sole()->id));

    expect((float) $product->shots()->sole()->duration_seconds)->toBe(3.5);
});

// ─────────────────────────────────────────────────────────────
// 失敗路徑
// ─────────────────────────────────────────────────────────────

it('fails the shot when the tts result has no s3 url', function () {
    // Lambda 只讀得到 S3。靜默接受 remote_url = null 的話，要等渲染完才發現整支片沒聲音。
    $product = voiceoverProduct();
    $shot = $product->shots()->sole();
    spyTts(['audio_url' => 'http://localhost/storage/voiceovers/a.mp3', 'remote_url' => null, 'duration_seconds' => 3.0]);

    dispatch_sync(new GenerateShotVoiceoverJob($shot->id));
    $shot->refresh();

    expect($shot->voiceover_status)->toBe('failed')
        // 失敗就不該留下半套資料讓後續誤以為可用
        ->and($shot->voiceover_remote_url)->toBeNull()
        ->and($shot->voiceover_duration_sec)->toBeNull()
        ->and($product->voiceovers()->sole()->status)->toBe('failed')
        ->and($product->voiceovers()->sole()->error_message)->toContain('S3 上傳失敗')
        // 有失敗 → AssetsPartial（才會出現「重試失敗素材」那顆按鈕）
        ->and($product->refresh()->status)->toBe(ProductStatus::AssetsPartial);
});

it('treats an empty string remote url as a failure too', function () {
    // blank() 而不是 is_null()：Azure 回空字串一樣是沒上傳成功
    $product = voiceoverProduct();
    spyTts(['audio_url' => '/a.mp3', 'remote_url' => '', 'duration_seconds' => 3.0]);

    dispatch_sync(new GenerateShotVoiceoverJob($product->shots()->sole()->id));

    expect($product->shots()->sole()->voiceover_status)->toBe('failed');
});

it('records the exception message when tts blows up', function () {
    $product = voiceoverProduct();
    $shot = $product->shots()->sole();
    spyTts(throws: new RuntimeException('Azure TTS 503 Service Unavailable'));

    dispatch_sync(new GenerateShotVoiceoverJob($shot->id));

    expect($shot->refresh()->voiceover_status)->toBe('failed')
        ->and($shot->voiceover_remote_url)->toBeNull()
        ->and($product->voiceovers()->sole()->error_message)->toBe('Azure TTS 503 Service Unavailable')
        ->and($product->voiceovers()->sole()->text)->toBe($shot->voiceover_text)
        ->and($product->refresh()->status)->toBe(ProductStatus::AssetsPartial);
});

it('does not rethrow, so one bad shot never kills the rest of the batch', function () {
    // tries = 1 代表沒有自動重試；若例外往外丟，queue 會把它記成 failed job
    // 而商品永遠停在 assets_generating（沒人呼叫 assetsSettled）。
    $product = voiceoverProduct(shots: 2);
    $shots = $product->shots()->orderBy('shot_order')->get();
    $spy = spyTts(throws: new RuntimeException('boom'));

    dispatch_sync(new GenerateShotVoiceoverJob($shots[0]->id));

    $spy->throws = null;
    dispatch_sync(new GenerateShotVoiceoverJob($shots[1]->id));

    expect($shots[0]->refresh()->voiceover_status)->toBe('failed')
        ->and($shots[1]->refresh()->voiceover_status)->toBe('done')
        ->and($product->refresh()->status)->toBe(ProductStatus::AssetsPartial);
});

it('truncates a huge error message to fit the column', function () {
    $product = voiceoverProduct();
    spyTts(throws: new RuntimeException(str_repeat('錯', 5000)));

    dispatch_sync(new GenerateShotVoiceoverJob($product->shots()->sole()->id));

    expect(mb_strlen((string) $product->voiceovers()->sole()->error_message))->toBe(2000);
});

// ─────────────────────────────────────────────────────────────
// 入口守衛 / 重跑不重複扣款
// ─────────────────────────────────────────────────────────────

it('skips a shot that is already done so a rerun never pays twice', function () {
    $product = voiceoverProduct();
    $shot = $product->shots()->sole();
    $spy = spyTts(['audio_url' => '/a.mp3', 'remote_url' => 'https://s3.test/a.mp3', 'duration_seconds' => 3.0]);

    dispatch_sync(new GenerateShotVoiceoverJob($shot->id));

    expect($spy->calls)->toHaveCount(1)
        ->and($shot->refresh()->voiceover_status)->toBe('done');

    $snapshot = fn () => [
        ...$shot->refresh()->only(['voiceover_remote_url', 'voiceover_duration_sec', 'duration_seconds']),
        'updated_at' => $shot->updated_at->toIso8601String(),   // updated_at 沒動 = DB 完全沒被寫
    ];
    $before = $snapshot();

    // 同一顆 job 再跑兩次（worker 重送、operator 重按）
    dispatch_sync(new GenerateShotVoiceoverJob($shot->id));
    dispatch_sync(new GenerateShotVoiceoverJob($shot->id));

    expect($spy->calls)->toHaveCount(1)                          // 沒有第二次付費呼叫
        ->and($product->voiceovers()->count())->toBe(1)          // 沒有重複的計費紀錄
        ->and($snapshot())->toBe($before);
});

it('only picks up shots in the pending state', function (string $status) {
    $product = voiceoverProduct(shotAttributes: ['voiceover_status' => $status]);
    $spy = spyTts();

    dispatch_sync(new GenerateShotVoiceoverJob($product->shots()->sole()->id));

    expect($spy->calls)->toBe([])
        ->and($product->shots()->sole()->voiceover_status)->toBe($status)
        ->and($product->voiceovers()->count())->toBe(0);
})->with(['done', 'failed', 'skipped', 'processing']);

it('runs for a pending shot, as the control group for the guard above', function () {
    $product = voiceoverProduct(shotAttributes: ['voiceover_status' => 'pending']);
    $spy = spyTts();

    dispatch_sync(new GenerateShotVoiceoverJob($product->shots()->sole()->id));

    expect($spy->calls)->toHaveCount(1)
        ->and($product->shots()->sole()->voiceover_status)->toBe('done');
});

it('does nothing for an unknown shot id', function () {
    $spy = spyTts();

    dispatch_sync(new GenerateShotVoiceoverJob('00000000-0000-0000-0000-000000000000'));

    expect($spy->calls)->toBe([]);
});

it('synthesizes an empty string when the shot has no voiceover text', function () {
    // 不理想但是現行行為：GenerateAssetsJob 只對 filled(voiceover_text) 的鏡頭派工，
    // 所以這條路徑只會在手動 dispatch 時發生。釘住它，將來改成「空稿直接 skip」時會提醒。
    $product = voiceoverProduct(shotAttributes: ['voiceover_text' => null]);
    $spy = spyTts();

    dispatch_sync(new GenerateShotVoiceoverJob($product->shots()->sole()->id));

    expect($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['text'])->toBe('')
        ->and($product->voiceovers()->sole()->text)->toBe('');
});

// ─────────────────────────────────────────────────────────────
// Job 設定
// ─────────────────────────────────────────────────────────────

it('never auto-retries, because every attempt costs money', function () {
    $job = new GenerateShotVoiceoverJob('00000000-0000-0000-0000-000000000000');

    expect($job->tries)->toBe(1)
        ->and($job->timeout)->toBe(60);
});
