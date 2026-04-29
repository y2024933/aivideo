<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Shot;
use App\Services\Contracts\VideoGeneratorContract;
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
            $shot->update([
                'video_status' => 'failed',
                'video_error' => 'Polling timeout after 30 attempts',
            ]);
            Log::error('[PollKlingVideoJob::handle] Polling timeout', ['shot_id' => $this->shotId]);
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
            'failed' => $shot->update([
                'video_status' => 'failed',
                'video_error' => $result['error'] ?? 'Unknown error',
            ]),
            default => self::dispatch($this->shotId, $this->taskId, $this->pollCount + 1)->delay(now()->addSeconds(10)),
        };
    }

    private function handleSucceed(Shot $shot, array $result): void
    {
        $costPerVideo = (float) config('services.kling.cost_per_video', 0.21);

        $shot->update([
            'video_url' => $result['video_url'],
            'video_status' => 'done',
            'video_cost_usd' => $costPerVideo,
        ]);

        $shot->buildingCase->addCost($costPerVideo);
    }
}
