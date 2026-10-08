<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Enums\VideoProvider;

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

    /** 這個實作對應哪個 provider（給記帳、log、UI 顯示用） */
    public function name(): VideoProvider;

    /**
     * 設定是否齊全、現在真的送得出任務。
     *
     * ⚠️ false 必須讓呼叫端「明確失敗」而不是靜默跳過 —— operator 設了動畫供應商
     * 卻什麼都沒發生、也沒有錯誤訊息，是最難查的一種故障。
     */
    public function supports(): bool;

    /** USD／秒，用於成本預估與記帳（免費額度的 provider 回 0.0） */
    public function costPerSecond(): float;
}
