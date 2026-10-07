<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Shot;
use App\Services\Contracts\VideoGeneratorContract;
use App\Services\Pipeline;
use App\Services\VideoDownloader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class PollKlingVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $shotId,
        public readonly string $taskId,
        public readonly int $pollCount = 0,
    ) {}

    public function handle(VideoGeneratorContract $videoGenerator): void
    {
        $shot = Shot::find($this->shotId);

        if (! $shot || $shot->video_status === 'done') {
            return; // 已完成或已刪除，跳過
        }

        // 超過 30 次輪詢視為逾時
        if ($this->pollCount >= 30) {
            Log::error('[PollKlingVideoJob::handle] Polling timeout', ['shot_id' => $this->shotId]);
            $this->handleFailed($shot, 'Polling timeout after 30 attempts');

            return;
        }

        try {
            $result = $videoGenerator->queryTaskStatus($this->taskId);
        } catch (\Throwable $e) {
            Log::error('[PollKlingVideoJob::handle] Query failed', ['exception' => $e]);
            self::dispatch($this->shotId, $this->taskId, $this->pollCount + 1)->delay(now()->addSeconds(10));
            return;
        }

        match ($result['status']) {
            'succeed' => $this->handleSucceed($shot, $result),
            'failed' => $this->handleFailed($shot, $result['error'] ?? 'Unknown error'),
            default => self::dispatch($this->shotId, $this->taskId, $this->pollCount + 1)->delay(now()->addSeconds(10)),
        };
    }

    private function handleFailed(Shot $shot, string $error): void
    {
        $shot->update(['video_status' => 'failed', 'video_error' => $error]);

        if ($shot->product) {
            app(Pipeline::class)->assetsSettled($shot->product);
        }
    }

    private function handleSucceed(Shot $shot, array $result): void
    {
        $costPerVideo = (float) config('services.kling.cost_per_video', 0.21);
        $remoteUrl = $result['video_url'];

        try {
            $file = VideoDownloader::download($remoteUrl);
        } catch (\Throwable $e) {
            Log::error('[PollKlingVideoJob] Video download failed', [
                'shot_id' => $shot->id,
                'remote_url' => $remoteUrl,
                'error' => $e->getMessage(),
            ]);
            $this->handleFailed($shot, 'Video download failed: ' . $e->getMessage());

            return;
        }

        $shot->update([
            'video_url' => $file->localPath,
            // Kling 的 URL 會過期，渲染一律吃我們自己的 S3 副本
            'video_remote_url' => $file->remoteUrl ?? $remoteUrl,
            'video_status' => 'done',
            'video_cost_usd' => $costPerVideo,
        ]);

        $shot->product->addCost($costPerVideo);
        app(Pipeline::class)->assetsSettled($shot->product);
    }
}
