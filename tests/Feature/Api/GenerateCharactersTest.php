<?php

declare(strict_types=1);

use App\Enums\CaseStatus;
use App\Jobs\GenerateCharacterPreviewJob;
use App\Models\BuildingCase;
use App\Models\User;
use App\Services\Contracts\ImageGeneratorContract;
use App\Services\Stubs\StubImageGenerator;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->app->bind(ImageGeneratorContract::class, fn () => new StubImageGenerator());
    Sanctum::actingAs(User::factory()->create());
});

it('dispatches job and returns 202 immediately', function () {
    Queue::fake();

    $case = BuildingCase::create([
        'name' => '測試建案',
        'character_dna' => 'a cute orange cat in suit',
    ]);

    $response = $this->postJson("/api/cases/{$case->id}/generate-characters");

    $response->assertStatus(202);
    $response->assertJsonCount(1, 'character_options');

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::CharacterGenerating);

    Queue::assertPushed(GenerateCharacterPreviewJob::class, 1);
});

it('job generates image and updates status', function () {
    $case = BuildingCase::create([
        'name' => '測試建案',
        'character_dna' => 'a cute orange cat',
    ]);

    $option = $case->characterOptions()->create([
        'prompt' => 'a cute orange cat',
        'status' => 'pending',
    ]);

    $case->transitionTo(CaseStatus::CharacterGenerating, 'operator');

    \Illuminate\Support\Facades\Storage::fake('local');
    \Illuminate\Support\Facades\Http::fake([
        'fal.run/fal-ai/flux-pro/v1.1' => \Illuminate\Support\Facades\Http::response([
            'images' => [['url' => 'https://fal.media/test-image.jpg']],
            'request_id' => 'req_123',
        ]),
        'fal.media/*' => \Illuminate\Support\Facades\Http::response('fake-image-data', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    // 直接執行 job
    (new GenerateCharacterPreviewJob($option->id, $case->id, 'a cute orange cat'))->handle();

    $option->refresh();
    expect($option->status)->toBe('done');
    expect($option->image_url)->toContain('/storage/characters/');

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::CharacterPendingReview);
});

it('generates scenes using approved character reference image', function () {
    $case = BuildingCase::create([
        'name' => '場景測試建案',
        'character_dna' => 'orange cat',
    ]);

    $charOption = $case->characterOptions()->create([
        'prompt' => 'orange cat',
        'image_url' => 'https://placehold.co/1024x1792/orange/white?text=Char',
        'fal_request_id' => 'stub_123',
        'status' => 'done',
    ]);

    $case->update(['approved_character_id' => $charOption->id]);
    $case->transitionTo(CaseStatus::CharacterApproved, 'operator');

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
});

it('returns 422 when no approved character', function () {
    $case = BuildingCase::create(['name' => '無角色測試']);

    $response = $this->postJson("/api/cases/{$case->id}/generate-scenes");

    $response->assertStatus(422);
});
