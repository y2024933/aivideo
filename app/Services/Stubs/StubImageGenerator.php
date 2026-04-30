<?php

declare(strict_types=1);

namespace App\Services\Stubs;

use App\Services\Contracts\ImageGeneratorContract;

final class StubImageGenerator implements ImageGeneratorContract
{
    public function generateCharacterPreviews(string $prompt, int $count = 4): array
    {
        return collect(range(1, $count))->map(fn (int $i) => [
            'request_id' => 'stub_char_' . uniqid(),
            'image_url' => "https://placehold.co/1024x1792/orange/white?text=Character+{$i}",
        ])->all();
    }

    public function generateSceneImage(string $prompt, string $referenceImageUrl, ?string $model = null): array
    {
        return [
            'request_id' => 'stub_scene_' . uniqid(),
            'image_url' => 'https://placehold.co/1024x1792/blue/white?text=Scene',
        ];
    }
}
