<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\ImageGeneratorContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class FalKontextImageGenerator implements ImageGeneratorContract
{
    private const MODEL_FLUX_PRO = 'fal-ai/flux-pro/v1.1';
    private const MODEL_KONTEXT = 'fal-ai/flux-pro/kontext';
    private const SYNC_BASE_URL = 'https://fal.run';

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.fal.key')
            ?? throw new RuntimeException('FAL_API_KEY is not configured');
    }

    public function generateCharacterPreviews(string $prompt, int $count = 4): array
    {
        // 用同步 API 逐一產圖（每張 ~10-15 秒）
        return collect(range(1, $count))->map(function () use ($prompt) {
            $result = $this->syncRequest(self::MODEL_FLUX_PRO, [
                'prompt' => $prompt,
                'image_size' => 'portrait_16_9',
                'num_images' => 1,
            ]);

            return [
                'request_id' => $result['request_id'] ?? null,
                'image_url' => $result['images'][0]['url'] ?? null,
            ];
        })->all();
    }

    public function generateSceneImage(string $prompt, string $referenceImageUrl): array
    {
        $result = $this->syncRequest(self::MODEL_KONTEXT, [
            'prompt' => $prompt,
            'image_url' => $referenceImageUrl,
            'image_size' => 'portrait_16_9',
            'num_images' => 1,
        ]);

        return [
            'request_id' => 'fal_' . uniqid(),
            'image_url' => $result['images'][0]['url'] ?? null,
        ];
    }

    /**
     * 同步呼叫 fal.ai（阻塞等待結果）
     */
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
