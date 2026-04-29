<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface ImageGeneratorContract
{
    /**
     * 產生角色預覽圖（4 張）
     * @return array<int, array{request_id: string, image_url: string|null}>
     */
    public function generateCharacterPreviews(string $prompt, int $count = 4): array;

    /**
     * 用參考角色圖產生場景圖
     */
    public function generateSceneImage(string $prompt, string $referenceImageUrl): array;
}
