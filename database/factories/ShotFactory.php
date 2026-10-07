<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Shot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shot>
 */
final class ShotFactory extends Factory
{
    protected $model = Shot::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'shot_id' => 'S01',
            'shot_order' => 1,
            'role' => 'feature',
            'duration_seconds' => 3.5,
            'scene_description' => '耳機特寫，背景虛化',
            'subtitle' => '降噪開啟後 世界瞬間安靜',
            'subtitle_has_simplified' => false,
            'ken_burns' => 'zoomIn',
            'fit' => 'contain',
            'video_provider' => 'none',
            'video_status' => 'skipped',
            'voiceover_status' => 'skipped',
        ];
    }

    /** 含簡體字的字幕，用來測合規攔截 */
    public function withSimplified(): self
    {
        return $this->state(fn () => ['subtitle' => '降噪开启后 世界瞬间安静', 'subtitle_has_simplified' => true]);
    }

    public function brollDone(): self
    {
        return $this->state(fn () => [
            'video_provider' => 'kling',
            'video_prompt' => 'slow push in on wireless earbuds, soft studio light',
            'video_url' => '/storage/videos/shot-broll.mp4',
            'video_remote_url' => 'https://remotionlambda-test.s3.amazonaws.com/videos/shot-broll.mp4',
            'video_request_id' => 'task_'.fake()->uuid(),
            'video_status' => 'done',
            'video_cost_usd' => 0.21,
        ]);
    }

    public function ttsDone(): self
    {
        return $this->state(fn () => [
            'voiceover_text' => '降噪開啟後，世界瞬間安靜。',
            'voiceover_url' => '/storage/voiceovers/shot.mp3',
            'voiceover_remote_url' => 'https://remotionlambda-test.s3.amazonaws.com/voiceovers/shot.mp3',
            'voiceover_status' => 'done',
            'voiceover_voice_id' => 'zh-TW-HsiaoChenNeural',
            'voiceover_duration_sec' => 3.5,
        ]);
    }
}
