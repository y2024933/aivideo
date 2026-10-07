<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Shot;
use App\Services\Contracts\TtsContract;
use App\Services\Llm\ShotDurationEstimator;
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
 * P6：單一鏡頭配音。完成後依配音長度重算鏡頭秒數（ShotDurationEstimator::fromTts），
 * 否則畫面還是寫稿時用字幕估的秒數，配音會被下一鏡切掉或疊音。
 */
final class GenerateShotVoiceoverJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly string $shotId) {}

    public function handle(TtsContract $tts, Pipeline $pipeline): void
    {
        $shot = Shot::with('product')->find($this->shotId);

        if (! $shot || ! $shot->product || $shot->voiceover_status !== 'pending') {
            return;
        }

        $shot->update(['voiceover_status' => 'processing']);
        $text = (string) $shot->voiceover_text;
        $voice = $shot->product->voice_id_preferred ?: 'zh-TW-HsiaoChenNeural';

        try {
            $result = $tts->synthesize($text, $voice);

            // Lambda 只讀得到 S3，沒有 remote_url 的配音渲染時一定 404，當下就判失敗
            if (blank($result['remote_url'] ?? null)) {
                throw new RuntimeException('配音已生成但 S3 上傳失敗');
            }
        } catch (Throwable $e) {
            Log::error('[GenerateShotVoiceoverJob] 配音失敗', ['shot_id' => $shot->id, 'exception' => $e]);
            $shot->update(['voiceover_status' => 'failed']);
            $shot->product->voiceovers()->create(['shot_uuid' => $shot->id, 'text' => $text, 'voice_id' => $voice, 'status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 2000)]);
            $pipeline->assetsSettled($shot->product);

            return;
        }

        $seconds = (float) $result['duration_seconds'];

        $shot->update([
            'voiceover_url' => $result['audio_url'],
            'voiceover_remote_url' => $result['remote_url'],
            'voiceover_voice_id' => $voice,
            'voiceover_duration_sec' => $seconds,
            'voiceover_status' => 'done',
            'duration_seconds' => ShotDurationEstimator::fromTts($seconds, (string) $shot->subtitle, $shot->effectiveTransition()),
        ]);

        $shot->product->voiceovers()->create([
            'shot_uuid' => $shot->id,
            'text' => $text,
            'voice_id' => $voice,
            'audio_url' => $result['audio_url'],
            'remote_url' => $result['remote_url'],
            'duration_seconds' => $seconds,
            'status' => 'done',
        ]);

        $pipeline->assetsSettled($shot->product);
    }
}
