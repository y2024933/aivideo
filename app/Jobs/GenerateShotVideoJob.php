<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Shot;
use App\Services\Contracts\VideoGeneratorContract;
use App\Services\Pipeline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * P5：單一鏡頭送 Kling 圖轉影片，送出後交給 PollKlingVideoJob 輪詢。
 */
final class GenerateShotVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** 鏡頭沒填 video_prompt 時的預設：商品圖只能輕微運鏡，商品本體變形就是假廣告 */
    private const DEFAULT_PROMPT = 'Slow, smooth camera push-in on the product. The product stays exactly the same shape, color and label; no added text, logos, hands or people.';

    public int $tries = 1;

    public function __construct(public readonly string $shotId) {}

    public function handle(VideoGeneratorContract $generator, Pipeline $pipeline): void
    {
        $shot = Shot::with('product')->find($this->shotId);

        if (! $shot || ! $shot->product || $shot->video_status !== 'pending') {
            return;
        }

        $prompt = $shot->video_prompt ?: self::DEFAULT_PROMPT;

        try {
            // Kling 讀不到 localhost，一律給 S3 的公開 URL
            $task = $generator->submitImageToVideo(
                $shot->image_remote_url ?: throw new RuntimeException('鏡頭沒有已同步 S3 的圖片'),
                $prompt,
                (int) ceil((float) $shot->duration_seconds ?: 5),
            );
        } catch (Throwable $e) {
            Log::error('[GenerateShotVideoJob] 送出失敗', ['shot_id' => $shot->id, 'exception' => $e]);
            $shot->update(['video_status' => 'failed', 'video_error' => mb_substr($e->getMessage(), 0, 2000)]);
            $pipeline->assetsSettled($shot->product);

            return;
        }

        $shot->update(['video_status' => 'processing', 'video_request_id' => $task['task_id'], 'video_prompt' => $prompt]);
        PollKlingVideoJob::dispatch($shot->id, $task['task_id'])->delay(now()->addSeconds(10));
    }
}
