<?php

declare(strict_types=1);

namespace App\Services\Stubs;

use App\Models\Product;
use App\Services\Contracts\VideoEditorContract;

final class StubVideoEditor implements VideoEditorContract
{
    public function submitRender(Product $product): array
    {
        return ['render_id' => 'stub_render_' . uniqid()];
    }

    public function queryRenderStatus(string $renderId): array
    {
        return [
            'status' => 'completed',
            'video_url' => 'https://placehold.co/1080x1920.mp4',
            'error' => null,
        ];
    }
}
