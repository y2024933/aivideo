<?php

declare(strict_types=1);

use App\Jobs\PollKlingVideoJob;
use App\Models\BuildingCase;
use App\Models\Shot;
use App\Services\Contracts\VideoGeneratorContract;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['services.kling.cost_per_video' => 0.21]);

    $this->case = BuildingCase::create(['name' => 'Video Test']);
    $this->shot = $this->case->shots()->create([
        'shot_id' => 'S01',
        'shot_order' => 1,
        'flux_prompt' => 'test prompt',
        'image_url' => 'https://example.com/img.jpg',
        'image_status' => 'done',
        'video_status' => 'processing',
        'video_request_id' => 'task_123',
    ]);
});

it('updates shot to done when task succeeds', function () {
    $mock = Mockery::mock(VideoGeneratorContract::class);
    $mock->shouldReceive('queryTaskStatus')
        ->with('task_123')
        ->andReturn([
            'status' => 'succeed',
            'video_url' => 'https://kling.ai/video/result.mp4',
            'error' => null,
        ]);

    $this->app->instance(VideoGeneratorContract::class, $mock);

    (new PollKlingVideoJob($this->shot->id, 'task_123'))->handle($mock);

    $this->shot->refresh();
    expect($this->shot->video_status)->toBe('done');
    expect($this->shot->video_url)->toBe('https://kling.ai/video/result.mp4');
    expect((float) $this->shot->video_cost_usd)->toBe(0.21);

    // 確認 case 費用有增加
    $this->case->refresh();
    expect((float) $this->case->cost_usd)->toBe(0.21);
});

it('re-dispatches when task is still processing', function () {
    Queue::fake();

    $mock = Mockery::mock(VideoGeneratorContract::class);
    $mock->shouldReceive('queryTaskStatus')
        ->with('task_123')
        ->andReturn([
            'status' => 'processing',
            'video_url' => null,
            'error' => null,
        ]);

    (new PollKlingVideoJob($this->shot->id, 'task_123', 5))->handle($mock);

    Queue::assertPushed(PollKlingVideoJob::class, function ($job) {
        return $job->shotId === $this->shot->id
            && $job->taskId === 'task_123'
            && $job->pollCount === 6;
    });
});

it('updates shot to failed when task fails', function () {
    $mock = Mockery::mock(VideoGeneratorContract::class);
    $mock->shouldReceive('queryTaskStatus')
        ->with('task_123')
        ->andReturn([
            'status' => 'failed',
            'video_url' => null,
            'error' => 'Content moderation rejected',
        ]);

    (new PollKlingVideoJob($this->shot->id, 'task_123'))->handle($mock);

    $this->shot->refresh();
    expect($this->shot->video_status)->toBe('failed');
    expect($this->shot->video_error)->toBe('Content moderation rejected');
});

it('marks as failed on polling timeout (pollCount >= 30)', function () {
    $mock = Mockery::mock(VideoGeneratorContract::class);
    $mock->shouldNotReceive('queryTaskStatus');

    (new PollKlingVideoJob($this->shot->id, 'task_123', 30))->handle($mock);

    $this->shot->refresh();
    expect($this->shot->video_status)->toBe('failed');
    expect($this->shot->video_error)->toContain('timeout');
});
