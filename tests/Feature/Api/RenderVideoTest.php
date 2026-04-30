<?php

declare(strict_types=1);

use App\Enums\CaseStatus;
use App\Jobs\PollRemotionRenderJob;
use App\Models\BuildingCase;
use App\Models\User;
use App\Services\Contracts\VideoEditorContract;
use App\Services\Stubs\StubVideoEditor;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Queue::fake();
    $this->app->bind(VideoEditorContract::class, fn () => new StubVideoEditor());
    Sanctum::actingAs(User::factory()->create());
});

it('submits render and dispatches poll job when all shots done and voiceover exists', function () {
    $case = BuildingCase::create([
        'name' => '渲染測試建案',
        'status' => CaseStatus::ProducingFinal,
    ]);

    $case->shots()->createMany([
        ['shot_id' => 'S01', 'shot_order' => 1, 'flux_prompt' => 'p1', 'video_status' => 'done', 'video_url' => 'https://example.com/1.mp4'],
        ['shot_id' => 'S02', 'shot_order' => 2, 'flux_prompt' => 'p2', 'video_status' => 'done', 'video_url' => 'https://example.com/2.mp4'],
    ]);

    $case->voiceovers()->create([
        'text' => '測試配音',
        'voice_id' => 'zh-TW-HsiaoChenNeural',
        'audio_url' => 'https://example.com/vo.mp3',
        'status' => 'done',
    ]);

    $response = $this->postJson("/api/cases/{$case->id}/render-video");

    $response->assertOk();
    $response->assertJsonStructure(['render_id', 'message']);

    // 確認 render_id 已存入 case
    $case->refresh();
    expect($case->render_id)->not()->toBeNull();

    Queue::assertPushed(PollRemotionRenderJob::class, 1);
});

it('returns 422 when no shots are done', function () {
    $case = BuildingCase::create(['name' => '未完成測試']);

    $case->shots()->createMany([
        ['shot_id' => 'S01', 'shot_order' => 1, 'flux_prompt' => 'p1', 'video_status' => 'processing'],
        ['shot_id' => 'S02', 'shot_order' => 2, 'flux_prompt' => 'p2', 'video_status' => 'pending'],
    ]);

    $response = $this->postJson("/api/cases/{$case->id}/render-video");

    $response->assertStatus(422);
    $response->assertJsonPath('error', '尚無任何已完成的影片片段');

    Queue::assertNotPushed(PollRemotionRenderJob::class);
});

it('returns 422 when render_id already exists', function () {
    $case = BuildingCase::create([
        'name' => '重複渲染測試',
        'render_id' => 'existing-render-id',
    ]);

    $case->shots()->create([
        'shot_id' => 'S01',
        'shot_order' => 1,
        'flux_prompt' => 'p1',
        'video_status' => 'done',
        'video_url' => 'https://example.com/1.mp4',
    ]);

    $response = $this->postJson("/api/cases/{$case->id}/render-video");

    $response->assertStatus(422);
    $response->assertJsonPath('error', '已有渲染任務進行中');

    Queue::assertNotPushed(PollRemotionRenderJob::class);
});

it('returns 500 when video editor service fails', function () {
    $this->app->bind(VideoEditorContract::class, function () {
        return new class implements VideoEditorContract {
            public function submitRender(\App\Models\BuildingCase $case): array
            {
                throw new RuntimeException('Lambda invocation failed');
            }

            public function queryRenderStatus(string $renderId): array
            {
                return ['status' => 'failed', 'video_url' => null, 'error' => 'fail'];
            }
        };
    });

    $case = BuildingCase::create(['name' => '失敗測試']);
    $case->shots()->create([
        'shot_id' => 'S01',
        'shot_order' => 1,
        'flux_prompt' => 'p1',
        'video_status' => 'done',
        'video_url' => 'https://example.com/1.mp4',
    ]);
    $case->voiceovers()->create([
        'text' => '測試',
        'voice_id' => 'zh-TW-HsiaoChenNeural',
        'audio_url' => 'https://example.com/vo.mp3',
        'status' => 'done',
    ]);

    $response = $this->postJson("/api/cases/{$case->id}/render-video");

    $response->assertStatus(500);
    $response->assertJsonPath('error', '影片渲染提交失敗');
});

it('requires authentication', function () {
    app('auth')->forgetGuards();

    $case = BuildingCase::create(['name' => '未認證測試']);

    $response = $this->postJson("/api/cases/{$case->id}/render-video");

    $response->assertStatus(401);
});
