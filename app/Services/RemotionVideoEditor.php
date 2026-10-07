<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AudioMode;
use App\Models\Product;
use App\Models\Shot;
use App\Services\Contracts\VideoEditorContract;
use Illuminate\Support\Facades\Log;
use Remotion\LambdaPhp\PHPClient;
use Remotion\LambdaPhp\RenderParams;

final class RemotionVideoEditor implements VideoEditorContract
{
    /**
     * Lambda site 上 bundle 的契約版本。
     *
     * 改動 remotion/src 底下的 props 契約（新增、改名、移除任何 inputProps 欄位）時必須
     * 同步遞增這裡與 remotion/src/ProductVideo.jsx 的 BUILD_TAG，並重新
     * `cd remotion && npm run deploy`。不一致時 ProductVideo 會直接丟 Error，
     * 比渲染出一支「運鏡沒生效 / 字幕沒套用」的影片好 debug。
     */
    private const EXPECTED_BUILD_TAG = 'v2-kenburns';

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

    public function submitRender(Product $product): array
    {
        $inputProps = $this->buildInputProps($product);

        $client = $this->makeClient();

        $params = new RenderParams();
        $params->setComposition('ProductVideo');
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
     * 組裝 Remotion inputProps。
     *
     * 每個欄位在 remotion/src/ 都必須有消費者 —— 沒人讀的欄位只會讓
     * 「改了 PHP 但影片沒變」這種 bug 更難查。
     */
    private function buildInputProps(Product $product): array
    {
        $product->load(['shots.productImage']);

        $isTts = ($product->audio_mode instanceof AudioMode ? $product->audio_mode->value : (string) $product->audio_mode)
            === AudioMode::Tts->value;

        // renderableUrl() 為 null 就跳過該鏡：Lambda 讀不到 localhost，
        // 寧可少一鏡也不要用本地路徑 fallback 把整支渲染 404 掉。
        $shots = $product->shots
            ->filter(fn (Shot $shot) => $shot->renderableUrl() !== null)
            ->sortBy('shot_order')
            ->values()
            ->map(fn (Shot $shot) => [
                'kind' => $shot->video_status === 'done' ? 'video' : 'image',
                'imageUrl' => $shot->image_remote_url,
                'videoUrl' => $shot->video_remote_url,
                'durationSec' => (float) $shot->duration_seconds ?: 3.5,
                'kenBurns' => $shot->ken_burns ?: ($product->default_ken_burns ?: 'auto'),
                'fit' => $shot->fit ?? $shot->productImage?->suggestedFit() ?? 'contain',
                // 刻意不 fallback 到 voiceover_text：配音稿是口語長句，拿去當字幕會爆版
                'subtitle' => (string) $shot->subtitle,
                'voiceoverUrl' => $isTts ? $shot->voiceover_remote_url : null,
                'transition' => $shot->transition,
            ])->toArray();

        return [
            'expectBuildTag' => self::EXPECTED_BUILD_TAG,
            'fps' => config('video.fps', 30),
            'shots' => $shots,
            'subtitleSettings' => $product->subtitle_settings ?? config('video.subtitle_defaults'),
            'globalTransition' => $product->global_transition ?: 'crossfade',
            'watermark' => ['text' => $product->disclosure_prefix ?: config('compliance.disclosure_watermark')],
            'bgm' => $product->bgm_remote_url
                ? ['audioUrl' => $product->bgm_remote_url, 'volume' => (float) $product->bgm_volume]
                : null,
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
