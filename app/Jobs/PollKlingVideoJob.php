<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Shot;
use App\Services\Video\VideoGeneratorFactory;
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

    public function handle(VideoGeneratorFactory $factory): void
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

        // ⚠️ 必須用「這個 shot 自己的 provider」而不是全域綁定。
        //    VIDEO_PROVIDER 預設是 none，而 provider 可以逐商品／逐鏡頭覆寫 ——
        //    拿全域綁定會解析到 NullVideoGenerator，queryTaskStatus() 丟例外後
        //    被下面的 catch 吞掉重排，空轉 30 次才報「Polling timeout」，
        //    錯誤訊息還會誤導成「Kling 太慢」。
        $videoGenerator = $factory->resolveFor($shot);

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
