<?php

declare(strict_types=1);

namespace App\Services\Browser;

use App\Data\Browser\BrowserResult;
use App\Data\ShopeeItemRef;
use App\Enums\BrowserTaskType;
use App\Models\BrowserTask;
use App\Services\Contracts\BrowserAutomationContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * 透過 HTTP 呼叫 browser/ 目錄的 Fastify 服務。
 *
 * 刻意「不裝任何 PHP 的瀏覽器套件」：Chromium 必須跑在自己的容器（ipc: host、3GB 記憶體、
 * 固定併發 1），塞進 php-fpm 容器會互相拖垮，而且 queue worker 重啟就會留下殭屍 chrome。
 *
 * 重試策略：
 *   - 可重試：連線層失敗（服務還沒起來／被重啟）、HTTP 5xx
 *   - 不重試：HTTP 4xx（我們傳錯東西，重試幾次都一樣）、服務回 200 但 status=failed
 *             的業務錯誤（人機驗證、商品下架）
 *   - backoff 從 config 讀，測試可縮短成 0
 *
 * ⚠️ 重試只在這裡做。ScrapeShopeeProductJob 的 tries 必須是 1。
 */
final class PlaywrightBrowserAutomation implements BrowserAutomationContract
{
    private ?BrowserTask $lastTask = null;

    public function __construct(private readonly BrowserTaskRecorder $recorder) {}

    public function scrapeShopeeProduct(ShopeeItemRef $ref, array $options = []): BrowserResult
    {
        return $this->run('/tasks/shopee-product', BrowserTaskType::ShopeeScrapeProduct, [
            'url' => $ref->canonicalUrl,
            'shopId' => $ref->shopId,
            'itemId' => $ref->itemId,
            // 降級路徑預設開：拿到「部分欄位 + 標人工複核」遠優於整批匯入失敗
            'allowDomFallback' => (bool) ($options['allow_dom_fallback'] ?? true),
        ], $options);
    }

    public function fetchImagesFromUrl(string $url, array $options = []): BrowserResult
    {
        return $this->run('/tasks/external-images', BrowserTaskType::ExternalImageScrape, [
            'url' => $url,
            'minWidth' => (int) ($options['min_width'] ?? 600),
            'limit' => (int) ($options['limit'] ?? 20),
        ], $options);
    }

    public function checkSessions(array $profiles = []): BrowserResult
    {
        return $this->run('/tasks/shopee-session', BrowserTaskType::ShopeeSessionCheck, [
            'profiles' => array_values($profiles),
        ], ['max_attempts' => 1]);
    }

    /** 最近一次呼叫寫出的 browser_tasks 紀錄（Filament 連結與除錯用） */
    public function lastTask(): ?BrowserTask
    {
        return $this->lastTask;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     */
    private function run(string $endpoint, BrowserTaskType $type, array $payload, array $options): BrowserResult
    {
        $delays = $this->delays();
        $task = $this->lastTask = $this->recorder->start($type, $payload, [
            ...$options,
            'max_attempts' => (int) ($options['max_attempts'] ?? count($delays) + 1),
        ]);

        if (blank(config('services.browser.base_url'))) {
            // 生產機刻意留空 BROWSER_SERVICE_URL，誤派的 job 要立刻失敗而不是卡住
            return $this->finish($task, BrowserResult::fail(
                'browser_service_not_configured',
                '未設定 BROWSER_SERVICE_URL：這台機器沒有瀏覽器服務，請在開發機執行（docker compose --profile browser up -d）。',
            ));
        }

        $payload = [...$payload, 'jitter' => config('services.browser.window.jitter')];
        $attempts = max(1, min((int) $task->max_attempts, count($delays) + 1));
        $result = BrowserResult::fail('not_attempted', '未送出任何請求');

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $task->markRunning();
            [$result, $retryable] = $this->attempt($endpoint, $payload);

            if (! $retryable || $attempt === $attempts) {
                break;
            }

            Log::warning('[Browser] 重試', [
                'endpoint' => $endpoint, 'attempt' => $attempt,
                'error_code' => $result->errorCode, 'sleep' => $delays[$attempt - 1] ?? 0,
            ]);

            $this->sleep($delays[$attempt - 1] ?? 0);
        }

        return $this->finish($task, $result);
    }

    /**
     * 單次呼叫。
     *
     * @return array{0: BrowserResult, 1: bool} [結果, 是否可重試]
     */
    private function attempt(string $endpoint, array $payload): array
    {
        $startedAt = microtime(true);

        try {
            $response = Http::baseUrl(rtrim((string) config('services.browser.base_url'), '/'))
                ->withToken((string) config('services.browser.token'))
                ->connectTimeout((int) config('services.browser.connect_timeout', 10))
                ->timeout((int) config('services.browser.timeout', 120))
                ->acceptJson()
                ->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            return [BrowserResult::fail('connection_failed', '連不上瀏覽器服務：' . $e->getMessage(), $this->elapsed($startedAt)), true];
        }

        if ($response->serverError()) {
            return [BrowserResult::fail('http_' . $response->status(), $this->errorText($response), $this->elapsed($startedAt)), true];
        }

        if ($response->clientError()) {
            // 4xx 是我們自己傳錯（含 401 token 不對），重試只是浪費時間
            return [BrowserResult::fail('http_' . $response->status(), $this->errorText($response), $this->elapsed($startedAt)), false];
        }

        return [$this->toResult($response->json() ?? [], $this->elapsed($startedAt)), false];
    }

    /** @param array<string, mixed> $body */
    private function toResult(array $body, int $fallbackDuration): BrowserResult
    {
        $status = (string) ($body['status'] ?? BrowserResult::FAILED);

        if (! in_array($status, [BrowserResult::SUCCEEDED, BrowserResult::DEGRADED, BrowserResult::FAILED, BrowserResult::NEEDS_MANUAL], true)) {
            $status = BrowserResult::FAILED;
        }

        return new BrowserResult(
            status: $status,
            strategy: $body['strategy'] ?? null,
            data: (array) ($body['data'] ?? []),
            errorCode: $body['errorCode'] ?? null,
            errorMessage: $body['errorMessage'] ?? null,
            durationMs: (int) ($body['durationMs'] ?? $fallbackDuration),
            artifacts: array_filter((array) ($body['artifacts'] ?? [])),
        );
    }

    /**
     * 失敗時把 Fastify 留下的截圖／trace 搬到 S3 再寫進 browser_tasks。
     *
     * 一定要搬走：browser 容器的 artifacts 目錄是暫存，重建容器就沒了，而
     * 「為什麼抓不到」往往要看當下那張截圖才知道（驗證碼？改版？地區限制？）。
     */
    private function finish(BrowserTask $task, BrowserResult $result): BrowserResult
    {
        $paths = [];

        foreach ($result->artifacts as $kind => $remotePath) {
            if (! in_array($kind, ['screenshot', 'trace', 'har'], true) || blank($remotePath)) {
                continue;
            }

            $paths[$kind] = $this->archive($task, $kind, (string) $remotePath);
        }

        $this->recorder->finish($task, $result, $paths);

        return $result;
    }

    /** 下載單一 artifact 並上傳到 artifacts disk，回傳 disk key（失敗不影響主流程） */
    private function archive(BrowserTask $task, string $kind, string $remotePath): ?string
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('services.browser.base_url'), '/'))
                ->withToken((string) config('services.browser.token'))
                ->timeout(60)
                ->get('/artifacts/' . ltrim($remotePath, '/'));

            if (! $response->successful()) {
                return null;
            }

            $key = "browser/{$task->id}/{$kind}." . (pathinfo($remotePath, PATHINFO_EXTENSION) ?: 'bin');
            Storage::disk((string) config('services.browser.artifacts_disk', 's3'))->put($key, $response->body(), 'public');

            return $key;
        } catch (Throwable $e) {
            Log::warning('[Browser] artifact 搬移失敗', ['task' => $task->id, 'kind' => $kind, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** @return list<int> */
    private function delays(): array
    {
        $raw = array_filter(array_map('trim', explode(',', (string) config('services.browser.retry.delays', '10,30,90'))), 'strlen');

        return array_values(array_map('intval', $raw));
    }

    private function sleep(int $seconds): void
    {
        if ($seconds > 0) {
            sleep($seconds);
        }
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function errorText(Response $response): string
    {
        return mb_substr((string) (data_get($response->json(), 'errorMessage') ?? data_get($response->json(), 'error.message') ?? $response->body()), 0, 1000);
    }
}
