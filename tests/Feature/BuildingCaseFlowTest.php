<?php

declare(strict_types=1);

use App\Enums\CaseStatus;
use App\Models\BuildingCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
    app()->bind(\App\Services\Contracts\ImageGeneratorContract::class, fn () => new \App\Services\Stubs\StubImageGenerator());
});

it('completes the full stub flow: create → generate characters → approve', function () {
    // 1. 建立建案
    $response = $this->postJson('/api/cases', [
        'name' => '松韻苑',
        'builder_name' => '陽光建設',
        'location' => '台中市北屯區',
        'character_dna' => 'chubby orange tabby cat with blue turtleneck',
        'target_audience' => 'first_buyer',
        'tone' => 'warm_family',
    ]);

    $response->assertStatus(201);
    $caseId = $response->json('id');
    expect($caseId)->not->toBeNull();

    // 2. 產生角色預覽（stub）
    $response = $this->postJson("/api/cases/{$caseId}/generate-characters");
    $response->assertStatus(200);

    $options = $response->json('character_options');
    expect($options)->toHaveCount(1);
    expect($options[0]['image_url'])->toContain('placehold.co');

    // 3. 確認狀態為待審核
    $case = BuildingCase::find($caseId);
    expect($case->status)->toBe(CaseStatus::CharacterPendingReview);

    // 4. 核准角色
    $response = $this->postJson("/api/cases/{$caseId}/approve-character", [
        'character_option_id' => $options[0]['id'],
    ]);
    $response->assertStatus(200);

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::CharacterApproved);
    expect($case->approved_character_id)->toBe($options[0]['id']);

    // 5. 確認狀態歷史
    expect($case->statusHistory)->toHaveCount(3); // draft→generating, generating→pending, pending→approved
});

it('returns a case with relationships', function () {
    $case = BuildingCase::create([
        'name' => '測試建案',
        'character_dna' => 'test',
    ]);

    $case->shots()->create([
        'shot_id' => 'S01',
        'shot_order' => 1,
        'flux_prompt' => 'test prompt',
        'duration_seconds' => 4.0,
    ]);

    $response = $this->getJson("/api/cases/{$case->id}");
    $response->assertStatus(200);
    $response->assertJsonCount(1, 'shots');
    $response->assertJsonPath('shots.0.shot_id', 'S01');
});
