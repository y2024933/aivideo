<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BuildingCase;
use App\Services\Contracts\VideoEditorContract;
use Illuminate\Support\Facades\Log;
use Remotion\LambdaPhp\PHPClient;
use Remotion\LambdaPhp\RenderParams;

final class RemotionVideoEditor implements VideoEditorContract
{
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
    }

    public function submitRender(BuildingCase $case): array
    {
        $inputProps = $this->buildInputProps($case);

        $client = $this->makeClient();

        $params = new RenderParams();
        $params->setComposition('BuildingVideo');
        $params->setInputProps($inputProps);
        $params->setCodec('h264');
        $params->setFramesPerLambda(config('services.remotion.frames_per_lambda', 150));

        $response = $client->renderMediaOnLambda($params);

        if (empty($response->renderId)) {
            Log::error('[RemotionVideoEditor::submitRender] 回傳無 renderId', ['response' => (array) $response]);
            throw new \RuntimeException('Remotion Lambda submit failed: no renderId returned');
        }

        // 將 renderId + bucketName 編碼為 JSON 存入 render_id 欄位
        $renderData = json_encode([
            'renderId' => $response->renderId,
            'bucketName' => $response->bucketName,
        ]);

        return ['render_id' => $renderData];
    }

    public function queryRenderStatus(string $renderId): array
    {
        // 解碼 JSON 格式的 render_id（包含 renderId + bucketName）
        $data = json_decode($renderId, true);

        if (! is_array($data) || empty($data['renderId']) || empty($data['bucketName'])) {
            // 向下相容：如果是純字串 renderId，用 extractBucketName 取得 bucket
            $actualRenderId = $renderId;
            $bucketName = $this->extractBucketName();
        } else {
            $actualRenderId = $data['renderId'];
            $bucketName = $data['bucketName'];
        }

        $client = $this->makeClient();
        $progress = $client->getRenderProgress($actualRenderId, $bucketName);

        Log::info('[RemotionVideoEditor::queryRenderStatus]', [
            'renderId' => $actualRenderId,
            'done' => $progress->done,
            'overallProgress' => $progress->overallProgress,
            'fatalErrorEncountered' => $progress->fatalErrorEncountered,
        ]);

        if ($progress->fatalErrorEncountered) {
            $errorMessage = $progress->errors[0]->message ?? 'Remotion render encountered a fatal error';
            Log::error('[RemotionVideoEditor::queryRenderStatus] Fatal error', [
                'renderId' => $actualRenderId,
                'errors' => $progress->errors ?? [],
            ]);

            return [
                'status' => 'failed',
                'video_url' => null,
                'error' => $errorMessage,
            ];
        }

        if ($progress->done) {
            return [
                'status' => 'completed',
                'video_url' => $progress->outputFile,
                'error' => null,
            ];
        }

        return [
            'status' => 'rendering',
            'video_url' => null,
            'error' => null,
        ];
    }

    /**
     * 建立 Remotion PHPClient（測試時可透過 setClient 注入 mock）
     */
    private ?PHPClient $client = null;

    public function setClient(PHPClient $client): void
    {
        $this->client = $client;
    }

    protected function makeClient(): PHPClient
    {
        if ($this->client) {
            return $this->client;
        }

        // 傳 null 讓 AWS SDK 自動從環境變數讀取 credentials
        return new PHPClient(
            $this->region,
            $this->serveUrl,
            $this->functionName,
            null,
        );
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
                'transition' => $shot->transition,
            ])->toArray();

        return [
            'fps' => 30,
            'shots' => $shots,
            'subtitleSettings' => $case->subtitle_settings ?? [
                'fontSize' => 'medium',
                'color' => '#ffffff',
                'position' => 'bottom',
                'animation' => 'slideIn',
            ],
            'globalTransition' => $case->global_transition ?? 'crossfade',
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
     * 從 serve_url 推導 bucket name（向下相容用）
     */
    private function extractBucketName(): string
    {
        $parsed = parse_url($this->serveUrl, PHP_URL_HOST);

        return $parsed ? explode('.', $parsed)[0] : 'remotionlambda-' . $this->region;
    }
}
