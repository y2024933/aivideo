<?php

declare(strict_types=1);

use App\Enums\CaseStatus;
use App\Models\BuildingCase;
use App\Models\User;
use App\Services\Contracts\ImageGeneratorContract;
use App\Services\Stubs\StubImageGenerator;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // 確保使用 stub，不打真實 API
    $this->app->bind(ImageGeneratorContract::class, fn () => new StubImageGenerator());
    Sanctum::actingAs(User::factory()->create());
});

it('generates characters and transitions status correctly', function () {
    $case = BuildingCase::create([
        'name' => '測試建案',
        'character_dna' => 'a cute orange cat in suit',
    ]);

    $response = $this->postJson("/api/cases/{$case->id}/generate-characters");

    $response->assertStatus(200);
    $response->assertJsonCount(4, 'character_options');

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::CharacterPendingReview);
});

it('transitions to CharacterFailed when image generator throws', function () {
    // 綁定一個會拋例外的假實作
    $this->app->bind(ImageGeneratorContract::class, function () {
        return new class implements ImageGeneratorContract {
            public function generateCharacterPreviews(string $prompt, int $count = 4): array
            {
                throw new RuntimeException('API connection failed');
            }

            public function generateSceneImage(string $prompt, string $referenceImageUrl): array
            {
                return [];
            }
        };
    });

    $case = BuildingCase::create([
        'name' => '失敗測試建案',
        'character_dna' => 'will fail',
    ]);

    $response = $this->postJson("/api/cases/{$case->id}/generate-characters");

    $response->assertStatus(500);
    $response->assertJsonPath('error', '角色生成失敗');

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::CharacterFailed);
});

it('generates scenes using approved character reference image', function () {
    $case = BuildingCase::create([
        'name' => '場景測試建案',
        'character_dna' => 'orange cat',
    ]);

    // 建立角色選項並核准
    $charOption = $case->characterOptions()->create([
        'prompt' => 'orange cat',
        'image_url' => 'https://placehold.co/1024x1792/orange/white?text=Char',
        'fal_request_id' => 'stub_123',
        'status' => 'done',
    ]);

    $case->update(['approved_character_id' => $charOption->id]);
    $case->transitionTo(CaseStatus::CharacterApproved, 'operator');

    // 建立待處理的 shots
    $case->shots()->createMany([
        ['shot_id' => 'S01', 'shot_order' => 1, 'flux_prompt' => 'a park with cat walking'],
        ['shot_id' => 'S02', 'shot_order' => 2, 'flux_prompt' => 'cat sitting by the river'],
    ]);

    $response = $this->postJson("/api/cases/{$case->id}/generate-scenes");

    $response->assertStatus(200);
    $response->assertJsonCount(2, 'shots');

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::ImagesPendingReview);
    expect((float) $case->cost_usd)->toBeGreaterThan(0);

    // 確認 shots 狀態已更新
    $case->shots->each(function ($shot) {
        expect($shot->image_status)->toBe('done');
        expect($shot->image_url)->toContain('placehold.co');
    });
});

it('returns 422 when no approved character', function () {
    $case = BuildingCase::create([
        'name' => '無角色測試',
    ]);

    $response = $this->postJson("/api/cases/{$case->id}/generate-scenes");

    $response->assertStatus(422);
    $response->assertJsonPath('error', '尚未核准角色或角色無圖片');
});
