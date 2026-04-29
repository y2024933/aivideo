<?php

declare(strict_types=1);

use App\Enums\CaseStatus;
use App\Jobs\PollKlingVideoJob;
use App\Models\BuildingCase;
use App\Models\User;
use App\Services\Contracts\VideoGeneratorContract;
use App\Services\Stubs\StubVideoGenerator;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Queue::fake();
    $this->app->bind(VideoGeneratorContract::class, fn () => new StubVideoGenerator());
    Sanctum::actingAs(User::factory()->create());
});

it('approves images and dispatches video jobs for done shots', function () {
    $case = BuildingCase::create([
        'name' => 'Approve Images Test',
        'status' => CaseStatus::ImagesPendingReview,
    ]);

    $case->shots()->createMany([
        ['shot_id' => 'S01', 'shot_order' => 1, 'flux_prompt' => 'scene 1', 'image_status' => 'done', 'image_url' => 'https://example.com/1.jpg'],
        ['shot_id' => 'S02', 'shot_order' => 2, 'flux_prompt' => 'scene 2', 'image_status' => 'done', 'image_url' => 'https://example.com/2.jpg'],
        ['shot_id' => 'S03', 'shot_order' => 3, 'flux_prompt' => 'scene 3', 'image_status' => 'failed', 'image_url' => null],
    ]);

    $response = $this->postJson("/api/cases/{$case->id}/approve-images");

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'shots');

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::ProducingFinal);

    // 只有 image_status=done 的 2 個 shot 會被 dispatch
    Queue::assertPushed(PollKlingVideoJob::class, 2);
});

it('transitions through ImagesApproved then ProducingFinal', function () {
    $case = BuildingCase::create([
        'name' => 'Status Transition Test',
        'status' => CaseStatus::ImagesPendingReview,
    ]);

    $case->shots()->create([
        'shot_id' => 'S01',
        'shot_order' => 1,
        'flux_prompt' => 'scene 1',
        'image_status' => 'done',
        'image_url' => 'https://example.com/1.jpg',
    ]);

    $this->postJson("/api/cases/{$case->id}/approve-images")->assertOk();

    // 確認狀態歷程有 ImagesApproved 和 ProducingFinal
    $history = $case->statusHistory()->pluck('to_status')->toArray();
    expect($history)->toContain('images_approved', 'producing_final');
});

it('returns 401 without authentication', function () {
    // 重設認證
    app('auth')->forgetGuards();

    $case = BuildingCase::create(['name' => 'Auth Test']);

    $response = $this->postJson("/api/cases/{$case->id}/approve-images");

    $response->assertStatus(401);
});
