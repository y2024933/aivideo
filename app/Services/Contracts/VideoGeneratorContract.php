<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface VideoGeneratorContract
{
    /**
     * 提交圖片轉影片任務（異步）
     * @return array{task_id: string}
     */
    public function submitImageToVideo(string $imageUrl, string $prompt, int $durationSeconds = 5): array;

    /**
     * 查詢影片生成狀態
     * @return array{status: string, video_url: string|null, error: string|null}
     */
    public function queryTaskStatus(string $taskId): array;
}
