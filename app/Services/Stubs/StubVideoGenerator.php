<?php

declare(strict_types=1);

namespace App\Services\Stubs;

use App\Enums\VideoProvider;
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
            'status' => 'succeed',
            'video_url' => 'https://placehold.co/1080x1920.mp4',
            'error' => null,
        ];
    }

    public function name(): VideoProvider
    {
        return VideoProvider::Stub;
    }

    /** 假的東西永遠「可用」，否則 stub 模式下整條素材流程都動不了 */
    public function supports(): bool
    {
        return true;
    }

    /** 不花錢。真實單價由各 provider 自己回報，不要在這裡假裝有成本 */
    public function costPerSecond(): float
    {
        return 0.0;
    }
}
