<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\ImageGeneratorContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class FalKontextImageGenerator implements ImageGeneratorContract
{
    public const MODEL_FLUX_PRO = 'fal-ai/flux-pro/v1.1';
    public const MODEL_KONTEXT = 'fal-ai/flux-pro/kontext';
    public const MODEL_IDEOGRAM = 'fal-ai/ideogram/v2/turbo';
    private const SYNC_BASE_URL = 'https://fal.run';

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.fal.key')
            ?? throw new RuntimeException('FAL_API_KEY is not configured');
    }

    public function generateCharacterPreviews(string $prompt, int $count = 4): array
    {
        $model = config('services.fal.character_model', self::MODEL_FLUX_PRO);

        return collect(range(1, $count))->map(function () use ($prompt, $model) {
            $input = ['prompt' => $prompt, 'num_images' => 1];

            if ($model === self::MODEL_IDEOGRAM) {
                $input['aspect_ratio'] = '9:16';
                $input['style'] = 'auto';
                $input['expand_prompt'] = false;
            } else {
                $input['image_size'] = 'portrait_16_9';
            }

            $result = $this->syncRequest($model, $input);

            return [
                'request_id' => $result['request_id'] ?? null,
                'image_url' => $result['images'][0]['url'] ?? null,
            ];
        })->all();
    }

    public function generateSceneImage(string $prompt, string $referenceImageUrl, ?string $model = null): array
    {
        $model = $model ?? self::MODEL_KONTEXT;

        if ($model === self::MODEL_IDEOGRAM) {
            // Ideogram 不支援參考圖，純 text-to-image
            $result = $this->syncRequest($model, [
                'prompt' => $prompt,
                'aspect_ratio' => '9:16',
                'style' => 'auto',
                'expand_prompt' => false,
                'num_images' => 1,
            ]);
        } else {
            // Flux Kontext 帶參考圖
            // 如果是本地路徑，轉 base64 data URI
            $imageRef = $referenceImageUrl;
            if (str_starts_with($referenceImageUrl, '/storage/')) {
                $localPath = storage_path('app/public/' . str_replace('/storage/', '', $referenceImageUrl));
                if (file_exists($localPath)) {
                    $mime = mime_content_type($localPath) ?: 'image/jpeg';
                    $imageRef = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($localPath));
                }
            }

            $result = $this->syncRequest($model, [
                'prompt' => $prompt,
                'image_url' => $imageRef,
                'image_size' => 'portrait_16_9',
                'num_images' => 1,
            ]);
        }

        return [
            'request_id' => $result['request_id'] ?? null,
            'image_url' => $result['images'][0]['url'] ?? null,
        ];
    }

    private function syncRequest(string $model, array $input): array
    {
        Log::info('[FalKontextImageGenerator] Calling fal.ai sync', ['model' => $model]);

        $response = Http::withHeaders($this->headers())
            ->timeout(120)
            ->post(self::SYNC_BASE_URL . "/{$model}", $input);

        if (! $response->successful()) {
            Log::error('[FalKontextImageGenerator] fal.ai failed', [
                'model' => $model,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException("fal.ai request failed: HTTP {$response->status()}");
        }

        return $response->json();
    }

    private function headers(): array
    {
        return [
            'Authorization' => "Key {$this->apiKey}",
            'Content-Type' => 'application/json',
        ];
    }
}
