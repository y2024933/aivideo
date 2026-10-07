<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Product;

interface VideoEditorContract
{
    /**
     * 組裝最終影片（異步）
     * @return array{render_id: string}
     */
    public function submitRender(Product $product): array;

    /**
     * 查詢渲染狀態
     * @return array{status: string, video_url: string|null, error: string|null}
     */
    public function queryRenderStatus(string $renderId): array;
}
