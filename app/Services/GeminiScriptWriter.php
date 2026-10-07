<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RetryableModelError;
use App\Data\Llm\ScriptOutput;
use App\Data\Llm\ScriptSchema;
use App\Models\Product;
use App\Services\Contracts\ScriptWriterContract;
use App\Services\Llm\ScriptPromptBuilder;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Google Gemini 寫稿（Google AI Studio 免費 tier）。
 *
 * 刻意直接打官方 REST endpoint 而不是裝第三方 PHP SDK：Gemini 沒有官方 PHP SDK，
 * 社群套件的維護品質不一，而我們只用到一個 endpoint，自己打反而可控。
 *
 *   POST {base_url}/models/{model}:generateContent
 *   Header: x-goog-api-key: {key}
 *
 * ⚠️ key 一律放 header，不要用文件上那種 ?key= query string —— URL 會進 Laravel 的
 * HTTP log、例外堆疊與 Horizon 的 payload，等於把金鑰寫進磁碟。
 *
 * ⚠️ 只有在 config('services.use_real_apis') 為 true 時才會被 ScriptWriterFactory 建出來。
 * 測試一律拿到 StubScriptWriter（Tests\TestCase::setUp 強制 use_real_apis=false）。
 */
final class GeminiScriptWriter implements ScriptWriterContract
{
    /** 這幾個 finishReason 都是「內容被安全機制擋下」，重寫同一份商品資料不會變好 */
    private const SAFETY_REASONS = ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION'];

    /** @var array{input: int, output: int, cache_read: int, cache_write: int, cost_usd: float, model: string} */
    private array $usage = ['input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0, 'cost_usd' => 0.0, 'model' => ''];

    private readonly string $apiKey;

    public function __construct(private readonly ScriptPromptBuilder $prompts)
    {
        $this->apiKey = (string) config('services.gemini.api_key');

        if ($this->apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY 未設定');
        }
    }

    public function write(Product $product, array $options = []): ScriptOutput
    {
        $models = $this->modelChain();
        $lastError = null;

        foreach ($models as $index => $model) {
            try {
                $body = $this->requestOnce($model, $product, $options);
            } catch (RetryableModelError $e) {
                // 503 high demand / 429 配額 / 連線逾時 —— 換下一個 model 再試。
                // 免費 tier 的熱門 model（如 gemini-3.8-flash）被擠爆是常態，
                // 而 -latest 這類 alias 也可能對特定帳號直接 hang。
                $lastError = $e;
                Log::warning('[GeminiScriptWriter] model 不可用，改用備援', [
                    'model' => $model,
                    'next' => $models[$index + 1] ?? null,
                    'reason' => $e->getMessage(),
                ]);

                continue;
            }

            $this->usage = $this->calcUsage((array) ($body['usageMetadata'] ?? []), (string) ($body['modelVersion'] ?? '') ?: $model);

            return ScriptSchema::hydrate($this->decode($this->extractText($body)));
        }

        throw new RuntimeException($this->exhaustedMessage($models, $lastError), previous: $lastError);
    }

    /**
     * 所有 model 都試完後的錯誤訊息。
     *
     * 要讓 operator 看了知道下一步做什麼，而不是只知道「失敗了」：
     * 配額用盡 → 等明天或切 Claude；全部過載 → 稍後再試；其他 → 看原始錯誤。
     *
     * @param  list<string>  $models
     */
    private function exhaustedMessage(array $models, ?Throwable $lastError): string
    {
        $detail = $lastError?->getMessage() ?? '未知錯誤';
        $tried = implode('、', $models);

        if (str_contains($detail, '429')) {
            return "Gemini 免費額度已用盡（已試 {$tried}，配額於太平洋時間午夜重置）。"
                .'可等額度恢復，或把這個商品的 provider 改成 Claude（約 $0.034/支）。原始錯誤：'.$detail;
        }

        if (preg_match('/\b(500|502|503|504)\b/', $detail) === 1) {
            return "Gemini 服務過載（已試 {$tried}）。免費 tier 的熱門 model 尖峰時段常見，"
                .'稍後重試通常就會成功；急件可改用 Claude provider。原始錯誤：'.$detail;
        }

        return "Gemini 所有 model 都不可用（已試：{$tried}）：{$detail}";
    }

    /**
     * 主要 model + 備援清單，去重後保持順序。
     *
     * @return list<string>
     */
    private function modelChain(): array
    {
        $fallbacks = array_filter(array_map('trim', explode(',', (string) config('services.gemini.fallback_models', ''))));

        return array_values(array_unique(array_filter([(string) config('services.gemini.model'), ...$fallbacks])));
    }

    /**
     * 打一次 generateContent。
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     *
     * @throws RetryableModelError  可以換 model 重試的錯誤
     * @throws RuntimeException     不該重試的錯誤（schema 錯、安全攔截、key 無效）
     */
    private function requestOnce(string $model, Product $product, array $options): array
    {
        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->timeout((int) config('services.gemini.timeout', 180))
                ->connectTimeout((int) config('services.gemini.connect_timeout', 15))
                ->acceptJson()
                ->post(rtrim((string) config('services.gemini.base_url'), '/')."/models/{$model}:generateContent", [
                    'systemInstruction' => ['parts' => [['text' => $this->systemInstruction($product)]]],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => $this->prompts->user(
                            $product,
                            $options['retry_report'] ?? null,
                            isset($options['retry_feedback']) ? (string) $options['retry_feedback'] : null,
                        )]],
                    ]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseSchema' => ScriptSchema::toGeminiSchema(),
                        'maxOutputTokens' => (int) config('services.gemini.max_output_tokens', 8000),
                        'temperature' => (float) config('services.gemini.temperature', 0.9),
                    ],
                ]);
        } catch (Throwable $e) {
            // 連線層的失敗（逾時、SSL 中斷）一律當成可換 model 重試
            throw new RetryableModelError("{$model} 連線失敗：{$e->getMessage()}", previous: $e);
        }

        if (in_array($response->status(), [429, 500, 502, 503, 504], true)) {
            throw new RetryableModelError(sprintf(
                '%s 回 HTTP %d：%s',
                $model,
                $response->status(),
                (string) ($response->json('error.message') ?? ''),
            ));
        }

        // 404 = model 已下架（例如 2.5 系列對新帳號），換下一個是對的
        if ($response->status() === 404) {
            throw new RetryableModelError("{$model} 不存在或已對新帳號下架");
        }

        return $this->assertOk($response);
    }

    public function lastUsage(): array
    {
        return $this->usage;
    }

    /**
     * ScriptPromptBuilder::system() 回的是 Anthropic 格式的三個 content block。
     * Gemini 的 systemInstruction 吃的是 parts 陣列而且沒有 cache_control 概念，
     * 所以把三段 text 串起來、忽略 cache_control 即可。
     *
     * Gemini 的快取是另外兩套機制、都不需要在 request 裡標記：
     *   - implicit caching：2.5 以後預設開啟，prefix 相同且超過模型門檻（3.x Flash 為
     *     4,096 token）就自動命中並折價，我們的 system prompt 剛好夠長。
     *   - explicit caching：CachedContent API，要另外建資源、按儲存時數收費。
     * 免費 tier 本來就不計費，兩者都沒有實作的必要。
     */
    private function systemInstruction(Product $product): string
    {
        $blocks = $this->prompts->system((string) ($product->compliance_profile ?: 'general'));

        return implode("\n\n", array_map(fn (array $block) => (string) ($block['text'] ?? ''), $blocks));
    }

    /**
     * HTTP 層與 candidate 層的錯誤分辨。訊息要讓 operator 看得懂該怎麼辦，
     * 因為它會被 GenerateScriptJob 原樣寫進 products.status_message。
     *
     * @return array<string, mixed>
     */
    private function assertOk(Response $response): array
    {
        if ($response->status() === 429) {
            throw new RuntimeException('Gemini 免費額度已用盡（每日配額），請稍後再試或改用 Claude provider');
        }

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Gemini 寫稿失敗（HTTP %d）：%s',
                $response->status(),
                (string) ($response->json('error.message') ?? mb_substr($response->body(), 0, 500)),
            ));
        }

        $body = (array) $response->json();

        // prompt 本身被擋時完全不會有 candidates，只有 promptFeedback.blockReason
        if (filled($block = $body['promptFeedback']['blockReason'] ?? null)) {
            throw new RuntimeException("Gemini 安全機制拒絕生成，請檢查商品內容（prompt 被擋：{$block}）");
        }

        $finish = (string) ($body['candidates'][0]['finishReason'] ?? '');

        if (in_array($finish, self::SAFETY_REASONS, true)) {
            throw new RuntimeException("Gemini 安全機制拒絕生成，請檢查商品內容（finishReason：{$finish}）");
        }

        if ($finish === 'MAX_TOKENS') {
            throw new RuntimeException('Gemini 輸出被截斷（達 maxOutputTokens 上限），請縮短商品描述或調高 GEMINI_MAX_OUTPUT_TOKENS');
        }

        return $body;
    }

    /** @param array<string, mixed> $body */
    private function extractText(array $body): string
    {
        $parts = (array) ($body['candidates'][0]['content']['parts'] ?? []);
        $text = trim(implode('', array_map(fn ($part) => is_array($part) ? (string) ($part['text'] ?? '') : '', $parts)));

        if ($text === '') {
            throw new RuntimeException('Gemini 沒有回傳任何內容（candidates 為空或不含 text part）');
        }

        return $text;
    }

    /** @return array<string, mixed> */
    private function decode(string $text): array
    {
        $decoded = json_decode($text, true);

        // responseMimeType 保證是 JSON，但「保證」偶爾會失效（例如模型回了 markdown fence）。
        // 不擋的話 null 會一路傳到 hydrate() 變成看不懂的 TypeError。
        if (! is_array($decoded)) {
            throw new RuntimeException('Gemini 回傳的不是合法 JSON：'.mb_substr($text, 0, 500));
        }

        return $decoded;
    }

    /**
     * 免費 tier 的成本是 0，但仍回真實 token 數 —— 之後升付費才有歷史數據可比。
     *
     * thinking token 與回答一起算 output 計費，所以併進 output。
     * cache_write 永遠是 0：Gemini 的 implicit cache 不收建立費（explicit cache 才有
     * 按時數計的儲存費，我們沒用）。
     *
     * @param  array<string, mixed>  $meta
     * @return array{input: int, output: int, cache_read: int, cache_write: int, cost_usd: float, model: string}
     */
    private function calcUsage(array $meta, string $model): array
    {
        $input = (int) ($meta['promptTokenCount'] ?? 0);
        $output = (int) ($meta['candidatesTokenCount'] ?? 0) + (int) ($meta['thoughtsTokenCount'] ?? 0);
        $cost = $input / 1_000_000 * (float) config('services.gemini.cost_per_mtok_input')
            + $output / 1_000_000 * (float) config('services.gemini.cost_per_mtok_output');

        return [
            'input' => $input,
            'output' => $output,
            'cache_read' => (int) ($meta['cachedContentTokenCount'] ?? 0),
            'cache_write' => 0,
            'cost_usd' => round($cost, 6),
            'model' => $model,
        ];
    }
}
