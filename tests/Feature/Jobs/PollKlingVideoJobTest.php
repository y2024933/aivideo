<?php

declare(strict_types=1);

use App\Jobs\PollKlingVideoJob;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Shot;
use App\Services\Contracts\VideoGeneratorContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['services.kling.cost_per_video' => 0.21]);

    $this->product = Product::create(['title' => 'Video Test', 'status' => ProductStatus::AssetsGenerating, 'video_provider' => 'kling']);
    $this->shot = $this->product->shots()->create([
        'shot_id' => 'S01',
        'shot_order' => 1,
        'image_url' => 'https://example.com/img.jpg',
        'image_remote_url' => 'https://example.com/img.jpg',
        'video_status' => 'processing',
        'video_request_id' => 'task_123',
    ]);
});

it('updates shot to done when task succeeds', function () {
    Storage::fake('public');
    Http::fake([
        'https://kling.ai/video/result.mp4' => Http::response('fake-video-content', 200),
    ]);

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
    expect($this->shot->video_url)->toStartWith('/storage/videos/');
    expect($this->shot->video_url)->toEndWith('.mp4');
    // Kling 的 URL 會過期，渲染要吃我們自己的 S3 副本
    expect($this->shot->video_remote_url)->not->toBe('https://kling.ai/video/result.mp4');
    expect((float) $this->shot->video_cost_usd)->toBe(0.21);

    // 確認檔案已同時存入 public disk 與 S3
    $storagePath = str_replace('/storage/', '', $this->shot->video_url);
    Storage::disk('public')->assertExists($storagePath);
    Storage::disk('s3')->assertExists($storagePath);

    // 確認商品費用有增加
    $this->product->refresh();
    expect((float) $this->product->cost_usd)->toBe(0.21);

    // 唯一鏡頭完成 → 推進到素材待審核
    expect($this->product->status)->toBe(ProductStatus::AssetsPendingReview);
});

it('marks as failed when video download fails', function () {
    Http::fake([
        'https://kling.ai/video/result.mp4' => Http::response('Not Found', 404),
    ]);

    $mock = Mockery::mock(VideoGeneratorContract::class);
    $mock->shouldReceive('queryTaskStatus')
        ->with('task_123')
        ->andReturn([
            'status' => 'succeed',
            'video_url' => 'https://kling.ai/video/result.mp4',
            'error' => null,
        ]);

    (new PollKlingVideoJob($this->shot->id, 'task_123'))->handle($mock);

    $this->shot->refresh();
    expect($this->shot->video_status)->toBe('failed');
    expect($this->shot->video_error)->toContain('Video download failed');

    // 確認下載失敗不扣費
    $this->product->refresh();
    expect((float) $this->product->cost_usd)->toBe(0.0);

    // 有失敗鏡頭 → 轉為部分素材失敗
    expect($this->product->status)->toBe(ProductStatus::AssetsPartial);
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

it('keeps product in assets_generating while other shots are still processing', function () {
    $this->product->shots()->create(['shot_id' => 'S02', 'shot_order' => 2, 'video_status' => 'processing', 'video_request_id' => 'task_456']);

    $mock = Mockery::mock(VideoGeneratorContract::class);
    $mock->shouldReceive('queryTaskStatus')->with('task_123')->andReturn([
        'status' => 'failed',
        'video_url' => null,
        'error' => 'rejected',
    ]);

    (new PollKlingVideoJob($this->shot->id, 'task_123'))->handle($mock);

    expect($this->product->refresh()->status)->toBe(ProductStatus::AssetsGenerating);
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
