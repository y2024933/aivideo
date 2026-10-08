<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Jobs\GenerateAssetsJob;
use App\Jobs\SubmitRenderJob;
use App\Models\Product;
use App\Models\Shot;
use App\Services\Compliance\AdComplianceChecker;
use App\Services\Contracts\TtsContract;
use App\Services\Llm\ShotDurationEstimator;
use App\Services\Stubs\StubTts;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

// 派出去的 Job 會當場執行，一個 dispatch 就能驗整條 ② → ③ → 渲染 → ④ 的接力。
// 所有外部服務都是 TestCase 綁的 stub。
//
// ⚠️ sync 是 tests/TestCase::setUp() 用 config() 強制的，**不是** phpunit.xml 的功勞。
//    phpunit.xml:27 的 QUEUE_CONNECTION=sync 會被 .env.docker 的 redis 蓋掉
//    （docker compose 注入成容器真實環境變數，$_SERVER 已有值時 phpunit 蓋不過）。
//    靠 phpunit.xml 的話 dispatch() 會把 job 丟進 Redis，測試只會看到「狀態沒變」。

/** 腳本已核准、可以直接進素材階段的商品 */
function scriptApprovedProduct(array $attributes = [], int $shots = 3): Product
{
    return Product::factory()->renderable($shots)->create([
        'status' => ProductStatus::ScriptApproved,
        'compliance_passed' => true,
        'compliance_rules_fingerprint' => app(AdComplianceChecker::class)->rulesFingerprint(),
        ...$attributes,
    ]);
}

function withVoiceoverText(Product $product): void
{
    Shot::where('product_id', $product->id)->update(['voiceover_text' => '這副耳機戴上去真的很安靜', 'voiceover_status' => 'pending']);
}

it('純商品圖：素材階段自動放行，一路渲染到 ④ 成品待審核', function () {
    $product = scriptApprovedProduct();

    dispatch(new GenerateAssetsJob($product->id));

    $product->refresh();
    expect($product->status)->toBe(ProductStatus::FinalPendingReview)
        ->and($product->final_video_remote_url)->toBe('https://placehold.co/1080x1920.mp4')
        ->and($product->statusHistory()->where('to_status', 'assets_approved')->value('triggered_by'))->toBe('autopilot');
});

it('TTS 模式：每鏡配音完成、依配音長度重算秒數、寫 Voiceover 紀錄後才渲染', function () {
    $product = scriptApprovedProduct(['audio_mode' => 'tts', 'voice_id_preferred' => 'zh-TW-YunJheNeural']);
    withVoiceoverText($product);

    dispatch(new GenerateAssetsJob($product->id));

    $product->refresh();
    $shot = $product->shots()->first();
    $ttsSeconds = mb_strlen('這副耳機戴上去真的很安靜') * 0.3;   // StubTts 的估法

    expect($product->status)->toBe(ProductStatus::FinalPendingReview)
        ->and($product->shots()->where('voiceover_status', '!=', 'done')->count())->toBe(0)
        ->and($shot->voiceover_remote_url)->toBe('https://placehold.co/audio.mp3')
        ->and($shot->voiceover_voice_id)->toBe('zh-TW-YunJheNeural')
        ->and((float) $shot->duration_seconds)->toBe(ShotDurationEstimator::fromTts($ttsSeconds, (string) $shot->subtitle, $shot->effectiveTransition()))
        ->and($product->voiceovers()->where('status', 'done')->count())->toBe(3);
});

it('bgm_only 模式不會因為寫稿留下的 pending 配音而卡住', function () {
    $product = scriptApprovedProduct(['audio_mode' => 'bgm_only']);
    withVoiceoverText($product);

    dispatch(new GenerateAssetsJob($product->id));

    expect($product->refresh()->status)->toBe(ProductStatus::FinalPendingReview)
        ->and($product->voiceovers()->count())->toBe(0);
});

it('有 Kling 動畫時停在 ③ 素材待審核，不自動渲染', function () {
    Storage::fake('public');
    Http::fake(['placehold.co/*' => Http::response('fake-mp4', 200)]);
    // P6 起 kling 要「設定齊全」才派工（缺 key 會明確失敗），這裡釘死假金鑰，
    // 不要讓這條測試的結果取決於開發機 .env 有沒有真的 KLING_ACCESS_KEY
    config(['services.kling.access_key' => 'test-ak', 'services.kling.secret_key' => 'test-sk-must-be-at-least-32-bytes-long']);
    $product = scriptApprovedProduct(['video_provider' => 'kling']);

    dispatch(new GenerateAssetsJob($product->id));

    $product->refresh();
    expect($product->status)->toBe(ProductStatus::AssetsPendingReview)
        ->and($product->shots()->where('video_status', 'done')->count())->toBe(3)
        ->and($product->shots()->first()->video_request_id)->toStartWith('stub_video_');
});

it('autopilot.assets 關閉時純商品圖也停在 ③', function () {
    config(['video.autopilot.assets' => false]);
    $product = scriptApprovedProduct();

    dispatch(new GenerateAssetsJob($product->id));

    expect($product->refresh()->status)->toBe(ProductStatus::AssetsPendingReview);
});

it('配音部分失敗轉 AssetsPartial，重試只補失敗的鏡頭', function () {
    $product = scriptApprovedProduct(['audio_mode' => 'tts']);
    withVoiceoverText($product);
    $failing = $product->shots()->orderBy('shot_order')->first();

    $tts = new class implements TtsContract
    {
        public array $texts = [];

        public bool $broken = true;

        public function synthesize(string $text, string $voiceName = 'zh-TW-HsiaoChenNeural'): array
        {
            $this->texts[] = $text;

            if ($this->broken && $text === 'S01') {
                throw new RuntimeException('Azure 503');
            }

            return (new StubTts())->synthesize($text, $voiceName);
        }
    };
    app()->instance(TtsContract::class, $tts);
    $failing->update(['voiceover_text' => 'S01']);

    dispatch(new GenerateAssetsJob($product->id));

    expect($product->refresh()->status)->toBe(ProductStatus::AssetsPartial)
        ->and($failing->refresh()->voiceover_status)->toBe('failed')
        ->and($product->voiceovers()->where('status', 'failed')->value('error_message'))->toBe('Azure 503');

    $tts->broken = false;
    $tts->texts = [];
    dispatch(new GenerateAssetsJob($product->id));

    expect($tts->texts)->toBe(['S01'])
        ->and($product->refresh()->status)->toBe(ProductStatus::FinalPendingReview);
});

it('配音沒有 S3 網址時判失敗（Lambda 讀不到本地檔）', function () {
    $product = scriptApprovedProduct(['audio_mode' => 'tts']);
    withVoiceoverText($product);
    app()->instance(TtsContract::class, new class implements TtsContract
    {
        public function synthesize(string $text, string $voiceName = 'zh-TW-HsiaoChenNeural'): array
        {
            return ['audio_url' => 'http://localhost/storage/voiceovers/a.mp3', 'remote_url' => null, 'duration_seconds' => 3.0];
        }
    });

    dispatch(new GenerateAssetsJob($product->id));

    expect($product->refresh()->status)->toBe(ProductStatus::AssetsPartial)
        ->and($product->shots()->where('voiceover_status', 'failed')->count())->toBe(3);
});

it('渲染前 gate 不過時轉 RenderFailed 並把原因寫進 status_message', function () {
    $product = scriptApprovedProduct(['status' => ProductStatus::AssetsApproved, 'compliance_passed' => false]);

    dispatch(new SubmitRenderJob($product->id));

    $product->refresh();
    expect($product->status)->toBe(ProductStatus::RenderFailed)
        ->and($product->status_message)->toContain('合規檢查未通過')
        ->and($product->render_id)->toBeNull();
});

it('不在入口狀態的商品不會被派工', function () {
    $product = scriptApprovedProduct(['status' => ProductStatus::ScriptPendingReview]);

    dispatch(new GenerateAssetsJob($product->id));
    dispatch(new SubmitRenderJob($product->id));

    expect($product->refresh()->status)->toBe(ProductStatus::ScriptPendingReview);
});
