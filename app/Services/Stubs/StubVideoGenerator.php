<?php

declare(strict_types=1);

namespace App\Services\Stubs;

use App\Services\Contracts\VideoGeneratorContract;

final class StubVideoGenerator implements VideoGeneratorContract
{
    public function submitImageToVideo(string $imageUrl, string $prompt, int $durationSeconds = 5): array
    {
        return ['task_id' => 'stub_video_' . uniqid()];
    }

    public function queryTaskStatus(string $taskId): array
    {
        return [
            'status' => 'completed',
            'video_url' => 'https://placehold.co/1080x1920.mp4',
            'error' => null,
        ];
    }
}
