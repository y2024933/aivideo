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
    private const QUEUE_BASE_URL = 'https://queue.fal.run';
    private const TIMEOUT_SECONDS = 180;

    private string $apiKey;
    private int $pollIntervalSeconds;

    public function __construct(?int $pollIntervalSeconds = null)
    {
        $this->apiKey = config('services.fal.key')
            ?? throw new RuntimeException('FAL_API_KEY is not configured');
        $this->pollIntervalSeconds = $pollIntervalSeconds
            ?? (int) config('services.fal.poll_interval', 3);
    }

    /** @inheritDoc */
    public function generateCharacterPreviews(string $prompt, int $count = 4): array
    {
        $input = [
            'prompt' => $prompt,
            'image_size' => 'portrait_16_9',
            'num_images' => 1,
        ];

        // 批次 submit 拿到所有 request_id
        $requestIds = collect(range(1, $count))
            ->map(fn () => $this->submitToQueue(self::MODEL_FLUX_PRO, $input))
            ->all();

        // 並行 poll 等全部完成
        return collect($requestIds)->map(function (string $requestId) {
            $result = $this->pollUntilDone(self::MODEL_FLUX_PRO, $requestId);

            return [
                'request_id' => $result['request_id'],
                'image_url' => $result['images'][0]['url'] ?? null,
            ];
        })->all();
    }

    /** @inheritDoc */
    public function generateSceneImage(string $prompt, string $referenceImageUrl): array
    {
        $result = $this->submitAndWait(self::MODEL_KONTEXT, [
            'prompt' => $prompt,
            'image_url' => $referenceImageUrl,
            'image_size' => 'portrait_16_9',
            'num_images' => 1,
        ]);

        return [
            'request_id' => $result['request_id'],
            'image_url' => $result['images'][0]['url'] ?? null,
        ];
    }

    /**
     * 提交到 fal.ai queue 並輪詢至完成
     *
     * @return array{request_id: string, images: array}
     */
    private function submitAndWait(string $model, array $input): array
    {
        $requestId = $this->submitToQueue($model, $input);

        return $this->pollUntilDone($model, $requestId);
    }

    /** 提交任務到 queue，回傳 request_id */
    private function submitToQueue(string $model, array $input): string
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(30)
            ->post(self::QUEUE_BASE_URL . "/{$model}", $input);

        if (! $response->successful()) {
            Log::error('[FalKontextImageGenerator::submitToQueue] fal.ai submit failed', [
                'model' => $model,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException("fal.ai submit failed: HTTP {$response->status()}");
        }

        return $response->json('request_id')
            ?? throw new RuntimeException('fal.ai submit response missing request_id');
    }

    /**
     * 輪詢直到任務完成或超時
     *
     * @return array{request_id: string, images: array}
     */
    private function pollUntilDone(string $model, string $requestId): array
    {
        $deadline = time() + self::TIMEOUT_SECONDS;
        $statusUrl = self::QUEUE_BASE_URL . "/{$model}/requests/{$requestId}/status";
        $resultUrl = self::QUEUE_BASE_URL . "/{$model}/requests/{$requestId}";

        while (time() < $deadline) {
            if ($this->pollIntervalSeconds > 0) {
                sleep($this->pollIntervalSeconds);
            }

            $statusResponse = Http::withHeaders($this->headers())
                ->timeout(15)
                ->get($statusUrl);

            $status = $statusResponse->json('status');

            if ($status === 'COMPLETED') {
                $resultResponse = Http::withHeaders($this->headers())
                    ->timeout(15)
                    ->get($resultUrl);

                $data = $resultResponse->json();
                $data['request_id'] = $requestId;

                return $data;
            }

            if (in_array($status, ['FAILED', 'CANCELLED'], true)) {
                Log::error('[FalKontextImageGenerator::pollUntilDone] Task failed', [
                    'request_id' => $requestId,
                    'status' => $status,
                    'response' => $statusResponse->json(),
                ]);
                throw new RuntimeException("fal.ai task {$status}: {$requestId}");
            }
        }

        throw new RuntimeException("fal.ai task timed out after " . self::TIMEOUT_SECONDS . "s: {$requestId}");
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Authorization' => "Key {$this->apiKey}",
            'Content-Type' => 'application/json',
        ];
    }
}
