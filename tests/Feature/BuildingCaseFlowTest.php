<?php

declare(strict_types=1);

use App\Enums\CaseStatus;
use App\Jobs\GenerateCharacterPreviewJob;
use App\Models\BuildingCase;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
});

it('completes the full async flow: create → dispatch job → job generates → approve', function () {
    // 1. 建立建案
    $response = $this->postJson('/api/cases', [
        'name' => '松韻苑',
        'builder_name' => '陽光建設',
        'location' => '台中市北屯區',
        'character_dna' => 'chubby orange tabby cat',
        'target_audience' => 'first_buyer',
        'tone' => 'warm_family',
    ]);

    $response->assertStatus(201);
    $caseId = $response->json('id');

    // 2. 觸發角色生成（應該 dispatch job，立即回 202）
    Queue::fake();
    $response = $this->postJson("/api/cases/{$caseId}/generate-characters");
    $response->assertStatus(202);
    Queue::assertPushed(GenerateCharacterPreviewJob::class, 1);

    $case = BuildingCase::find($caseId);
    expect($case->status)->toBe(CaseStatus::CharacterGenerating);
    expect($case->characterOptions)->toHaveCount(1);

    // 3. 模擬 job 執行完成
    $option = $case->characterOptions->first();
    \Illuminate\Support\Facades\Storage::fake('local');
    Http::fake([
        'fal.run/fal-ai/flux-pro/v1.1' => Http::response([
            'images' => [['url' => 'https://fal.media/test.jpg']],
            'request_id' => 'req_test',
        ]),
        'fal.media/*' => Http::response('fake-image', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    (new GenerateCharacterPreviewJob($option->id, $caseId, 'chubby orange tabby cat'))->handle();

    $option->refresh();
    expect($option->status)->toBe('done');
    expect($option->image_url)->toContain('/storage/characters/');

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::CharacterPendingReview);

    // 4. 核准角色
    $response = $this->postJson("/api/cases/{$caseId}/approve-character", [
        'character_option_id' => $option->id,
    ]);
    $response->assertStatus(200);

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::CharacterApproved);
});

it('returns a case with relationships', function () {
    $case = BuildingCase::create(['name' => '測試建案', 'character_dna' => 'test']);

    $case->shots()->create([
        'shot_id' => 'S01',
        'shot_order' => 1,
        'flux_prompt' => 'test prompt',
        'duration_seconds' => 4.0,
    ]);

    $response = $this->getJson("/api/cases/{$case->id}");
    $response->assertStatus(200);
    $response->assertJsonCount(1, 'shots');
});
