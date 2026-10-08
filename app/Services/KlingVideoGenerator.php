<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\VideoProvider;
use App\Services\Contracts\VideoGeneratorContract;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class KlingVideoGenerator implements VideoGeneratorContract
{
    private const BASE_URL = 'https://api.klingai.com/v1/videos/image2video';

    private string $accessKey;
    private string $secretKey;

    public function __construct()
    {
        $this->accessKey = config('services.kling.access_key')
            ?? throw new RuntimeException('KLING_ACCESS_KEY is not configured');
        $this->secretKey = config('services.kling.secret_key')
            ?? throw new RuntimeException('KLING_SECRET_KEY is not configured');
    }

    /** @inheritDoc */
    public function submitImageToVideo(string $imageUrl, string $prompt, int $durationSeconds = 5): array
    {
        // 本地路徑轉 base64（Kling 需要公開 URL 或 base64）
        $imageRef = $imageUrl;
        if (str_starts_with($imageUrl, '/storage/') || str_starts_with($imageUrl, 'http://localhost')) {
            $localPath = storage_path('app/public/' . preg_replace('#^/storage/#', '', $imageUrl));
            if (file_exists($localPath)) {
                $imageRef = base64_encode(file_get_contents($localPath));
            }
        }

        $response = Http::withHeaders($this->headers())
            ->timeout(30)
            ->post(self::BASE_URL, [
                'model_name' => config('services.kling.model', 'kling-v2-5-turbo'),
                'image' => $imageRef,
                'prompt' => $prompt,
                'negative_prompt' => config('services.kling.negative_prompt', 'blurry, distorted architecture, warped lines, extra limbs, jittery camera, melting surfaces, face deformation'),
                'cfg_scale' => (float) config('services.kling.cfg_scale', 0.7),
                'duration' => (string) self::normalizeDuration($durationSeconds),
                'mode' => config('services.kling.mode', 'std'),
            ]);

        if (! $response->successful() || $response->json('code') !== 0) {
            Log::error('[KlingVideoGenerator::submitImageToVideo] Kling API submit failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException("Kling submit failed: HTTP {$response->status()}");
        }

        return ['task_id' => $response->json('data.task_id')];
    }

    /** @inheritDoc */
    public function queryTaskStatus(string $taskId): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->get(self::BASE_URL . "/{$taskId}");

        if (! $response->successful() || $response->json('code') !== 0) {
            Log::error('[KlingVideoGenerator::queryTaskStatus] Kling API query failed', [
                'task_id' => $taskId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['status' => 'failed', 'video_url' => null, 'error' => "HTTP {$response->status()}"];
        }

        $data = $response->json('data');
        $status = $data['task_status'] ?? 'unknown';
        $videoUrl = $data['task_result']['videos'][0]['url'] ?? null;

        return [
            'status' => $status,
            'video_url' => $videoUrl,
            'error' => $data['task_status_msg'] ?? null,
        ];
    }

    public function name(): VideoProvider
    {
        return VideoProvider::Kling;
    }

    /**
     * 兩把金鑰都要有才送得出任務。
     *
     * ⚠️ 直接讀 config 而不是看 $this->accessKey —— 建構子缺 key 就會丟例外，
     *    所以能拿到實例時這裡一定是 true；VideoGeneratorFactory 判斷可用性時
     *    是用 try/catch 建構，建不起來就當成 supports() = false。
     */
    public function supports(): bool
    {
        return filled(config('services.kling.access_key')) && filled(config('services.kling.secret_key'));
    }

    /** $0.21／5 秒 = $0.042／秒（V2.5 Turbo std） */
    public function costPerSecond(): float
    {
        return (float) config('services.kling.cost_per_video', 0.21) / 5;
    }

    /** V2.5 Turbo 只支援 5 和 10 秒，自動 round */
    public static function normalizeDuration(int $seconds): int
    {
        return $seconds <= 7 ? 5 : 10;
    }

    /** 產生 JWT token (HS256) */
    private function generateToken(): string
    {
        $now = time();

        return JWT::encode([
            'iss' => $this->accessKey,
            'exp' => $now + 1800,
            'nbf' => $now - 5,
        ], $this->secretKey, 'HS256', null, ['typ' => 'JWT']);
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->generateToken(),
            'Content-Type' => 'application/json',
        ];
    }
}
