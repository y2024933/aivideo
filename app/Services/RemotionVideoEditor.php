<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BuildingCase;
use App\Services\Contracts\VideoEditorContract;
use Aws\Lambda\LambdaClient;
use Illuminate\Support\Facades\Log;

final class RemotionVideoEditor implements VideoEditorContract
{
    private LambdaClient $lambda;
    private readonly string $functionName;
    private readonly string $serveUrl;
    private readonly string $region;

    public function __construct()
    {
        $this->region = config('services.remotion.region', 'us-east-1');
        $this->functionName = config('services.remotion.function_name')
            ?? throw new \RuntimeException('REMOTION_FUNCTION_NAME is not configured');
        $this->serveUrl = config('services.remotion.serve_url')
            ?? throw new \RuntimeException('REMOTION_SERVE_URL is not configured');

        $this->lambda = new LambdaClient([
            'region' => $this->region,
            'version' => 'latest',
            'credentials' => [
                'key' => config('services.remotion.key'),
                'secret' => config('services.remotion.secret'),
            ],
        ]);
    }

    public function submitRender(BuildingCase $case): array
    {
        $inputProps = $this->buildInputProps($case);

        $result = $this->lambda->invoke([
            'FunctionName' => $this->functionName,
            'Payload' => json_encode([
                'type' => 'start',
                'serveUrl' => $this->serveUrl,
                'composition' => 'BuildingVideo',
                'inputProps' => $inputProps,
                'codec' => 'h264',
                'imageFormat' => 'jpeg',
                'version' => config('services.remotion.version', '4.0.454'),
            ]),
        ]);

        $payload = json_decode($result['Payload']->getContents(), true);

        if (empty($payload['renderId'])) {
            Log::error('[RemotionVideoEditor::submitRender] Lambda 回傳無 renderId', ['payload' => $payload]);
            throw new \RuntimeException('Remotion Lambda submit failed: no renderId returned');
        }

        return ['render_id' => $payload['renderId']];
    }

    public function queryRenderStatus(string $renderId): array
    {
        $result = $this->lambda->invoke([
            'FunctionName' => $this->functionName,
            'Payload' => json_encode([
                'type' => 'status',
                'renderId' => $renderId,
                'bucketName' => $this->extractBucketName(),
            ]),
        ]);

        $payload = json_decode($result['Payload']->getContents(), true);

        return match ($payload['type'] ?? null) {
            'success' => [
                'status' => 'completed',
                'video_url' => $payload['outputUrl'] ?? $payload['url'] ?? null,
                'error' => null,
            ],
            'error' => [
                'status' => 'failed',
                'video_url' => null,
                'error' => $payload['message'] ?? 'Remotion render failed',
            ],
            default => [
                'status' => 'rendering',
                'video_url' => null,
                'error' => null,
            ],
        };
    }

    /**
     * 組裝 Remotion inputProps
     */
    private function buildInputProps(BuildingCase $case): array
    {
        $case->load(['shots']);

        $shots = $case->shots
            ->filter(fn ($shot) => $shot->video_status === 'done')
            ->sortBy('shot_order')
            ->values()
            ->map(fn ($shot) => [
                'videoUrl' => $shot->video_remote_url ?? url($shot->video_url),
                'durationSec' => (float) $shot->duration_seconds ?: 5,
                'clipDurationSec' => KlingVideoGenerator::normalizeDuration((int) ($shot->duration_seconds ?: 5)),
                'subtitle' => $shot->subtitle ?? $shot->voiceover_text ?? '',
                'isPublicFacility' => false,
                'voiceoverUrl' => $shot->voiceover_remote_url ?? ($shot->voiceover_url ? url($shot->voiceover_url) : null),
            ])->toArray();

        return [
            'fps' => 30,
            'shots' => $shots,
            'watermark' => [
                'text' => '3D 示意圖｜實品以建造完成後為準',
            ],
            'publicFacilityLabel' => '公設示意圖',
            'brand' => [
                'name' => $case->name,
                'slogan' => '',
            ],
        ];
    }

    /**
     * 從 serve_url 推導 bucket name（Remotion 慣例）
     */
    private function extractBucketName(): string
    {
        // Remotion Lambda 的 serve URL 格式：https://{bucket}.s3.{region}.amazonaws.com/...
        $parsed = parse_url($this->serveUrl, PHP_URL_HOST);

        return $parsed ? explode('.', $parsed)[0] : 'remotionlambda-' . $this->region;
    }
}
