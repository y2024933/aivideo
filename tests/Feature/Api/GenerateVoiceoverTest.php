<?php

declare(strict_types=1);

use App\Models\BuildingCase;
use App\Models\User;
use App\Services\Contracts\TtsContract;
use App\Services\Stubs\StubTts;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->app->bind(TtsContract::class, fn () => new StubTts());
    Sanctum::actingAs(User::factory()->create());
});

it('generates voiceover from shots voiceover_text', function () {
    $case = BuildingCase::create(['name' => '配音測試建案']);

    $case->shots()->createMany([
        ['shot_id' => 'S01', 'shot_order' => 1, 'flux_prompt' => 'p1', 'voiceover_text' => '歡迎來到泉宇建設'],
        ['shot_id' => 'S02', 'shot_order' => 2, 'flux_prompt' => 'p2', 'voiceover_text' => '位於市中心的豪宅'],
    ]);

    $response = $this->postJson("/api/cases/{$case->id}/generate-voiceover");

    $response->assertStatus(201);
    $response->assertJsonStructure(['id', 'text', 'voice_id', 'audio_url', 'duration_seconds', 'status']);
    $response->assertJsonPath('status', 'done');
    $response->assertJsonPath('voice_id', 'zh-TW-HsiaoChenNeural');

    // 確認 Voiceover record 已建立
    expect($case->voiceovers()->count())->toBe(1);
});

it('returns 422 when no voiceover text exists', function () {
    $case = BuildingCase::create(['name' => '空稿測試']);

    $case->shots()->create([
        'shot_id' => 'S01',
        'shot_order' => 1,
        'flux_prompt' => 'prompt',
        'voiceover_text' => null,
    ]);

    $response = $this->postJson("/api/cases/{$case->id}/generate-voiceover");

    $response->assertStatus(422);
    $response->assertJsonPath('error', '此建案無配音稿文字');
});

it('returns 500 when TTS service fails', function () {
    $this->app->bind(TtsContract::class, function () {
        return new class implements TtsContract {
            public function synthesize(string $text, string $voiceName = 'zh-TW-HsiaoChenNeural'): array
            {
                throw new RuntimeException('TTS service unavailable');
            }
        };
    });

    $case = BuildingCase::create(['name' => '失敗測試']);
    $case->shots()->create([
        'shot_id' => 'S01',
        'shot_order' => 1,
        'flux_prompt' => 'p1',
        'voiceover_text' => '這段文字會失敗',
    ]);

    $response = $this->postJson("/api/cases/{$case->id}/generate-voiceover");

    $response->assertStatus(500);
    $response->assertJsonPath('error', '配音生成失敗');
});

it('requires authentication', function () {
    // 清除認證
    app('auth')->forgetGuards();

    $case = BuildingCase::create(['name' => '未認證測試']);

    $response = $this->postJson("/api/cases/{$case->id}/generate-voiceover");

    $response->assertStatus(401);
});

it('combines shots text in shot_order', function () {
    $case = BuildingCase::create(['name' => '排序測試']);

    // 故意亂序建立，但 shot_order 有定義
    $case->shots()->createMany([
        ['shot_id' => 'S03', 'shot_order' => 3, 'flux_prompt' => 'p3', 'voiceover_text' => '第三段'],
        ['shot_id' => 'S01', 'shot_order' => 1, 'flux_prompt' => 'p1', 'voiceover_text' => '第一段'],
        ['shot_id' => 'S02', 'shot_order' => 2, 'flux_prompt' => 'p2', 'voiceover_text' => '第二段'],
    ]);

    $response = $this->postJson("/api/cases/{$case->id}/generate-voiceover");

    $response->assertStatus(201);

    $voiceover = $case->voiceovers()->first();
    // 文字應按 shot_order 排列
    expect($voiceover->text)->toBe("第一段\n第二段\n第三段");
});
