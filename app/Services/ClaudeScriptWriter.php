<?php

declare(strict_types=1);

namespace App\Services;

use Anthropic\Client;
use Anthropic\Messages\Usage;
use App\Data\Llm\ScriptOutput;
use App\Models\Product;
use App\Services\Contracts\ScriptWriterContract;
use App\Services\Llm\ScriptPromptBuilder;
use RuntimeException;
use Throwable;

/**
 * Anthropic Claude 寫稿。
 *
 * 用 structured output（outputConfig.format）而不是「請回我 JSON」的提示詞：
 * schema 由 App\Data\Llm\ScriptOutput 反射產生，API 端做約束解碼，
 * 因此不會收到被 markdown code fence 包住或少一個逗號的 JSON。
 *
 * ⚠️ 只有在 config('services.use_real_apis') 為 true 時才會被 AppServiceProvider 綁定。
 * 測試一律拿到 StubScriptWriter（Tests\TestCase::setUp 強制 use_real_apis=false）。
 */
final class ClaudeScriptWriter implements ScriptWriterContract
{
    /** @var array{input: int, output: int, cache_read: int, cache_write: int, cost_usd: float, model: string} */
    private array $usage = ['input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0, 'cost_usd' => 0.0, 'model' => ''];

    private readonly Client $client;

    /**
     * ⚠️ Client 刻意「不」放進建構子參數：Laravel 容器會自動解析 class 型別的參數
     * （即使宣告成 ?Client = null 也會先試著 build），Anthropic\Client 的建構子參數
     * 全是選用的，所以容器永遠能生出一個沒有 key 的 Client，缺 key 的檢查就永遠不會觸發。
     */
    public function __construct(private readonly ScriptPromptBuilder $prompts)
    {
        $apiKey = (string) config('services.anthropic.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('ANTHROPIC_API_KEY 未設定');
        }

        $this->client = new Client(
            apiKey: $apiKey,
            requestOptions: ['timeout' => (float) config('services.anthropic.timeout', 180)],
        );
    }

    public function write(Product $product, array $options = []): ScriptOutput
    {
        $model = (string) config('services.anthropic.model');

        try {
            $message = $this->client->messages->create(
                maxTokens: (int) config('services.anthropic.max_tokens'),
                messages: [[
                    'role' => 'user',
                    'content' => $this->prompts->user(
                        $product,
                        $options['retry_report'] ?? null,
                        isset($options['retry_feedback']) ? (string) $options['retry_feedback'] : null,
                    ),
                ]],
                model: $model,
                outputConfig: ['format' => ScriptOutput::class],
                system: $this->prompts->system((string) ($product->compliance_profile ?: 'general')),
            );
        } catch (Throwable $e) {
            throw new RuntimeException("Anthropic 寫稿失敗：{$e->getMessage()}", previous: $e);
        }

        $this->usage = $this->calcUsage($message->usage, $message->model !== '' ? (string) $message->model : $model);
        $output = $message->parsedOutput();

        // parsedOutput() 在 JSON 解析失敗時回 ['error' => ...]，stopReason 為 max_tokens 時回 null
        if (! $output instanceof ScriptOutput) {
            throw new RuntimeException('Anthropic 回傳無法解析為腳本：'.json_encode(
                is_array($output) ? $output : ['stop_reason' => $message->stopReason],
                JSON_UNESCAPED_UNICODE,
            ));
        }

        return $output;
    }

    public function lastUsage(): array
    {
        return $this->usage;
    }

    /**
     * 用回傳的實際 token 數算錢（不是估算）。
     * cache write 比 input 貴 25%、cache read 只要 10%，四種單價都不同，必須分開算。
     *
     * @return array{input: int, output: int, cache_read: int, cache_write: int, cost_usd: float, model: string}
     */
    private function calcUsage(Usage $usage, string $model): array
    {
        $counts = [
            'input' => $usage->inputTokens,
            'output' => $usage->outputTokens,
            'cache_read' => (int) ($usage->cacheReadInputTokens ?? 0),
            'cache_write' => (int) ($usage->cacheCreationInputTokens ?? 0),
        ];

        $rates = [
            'input' => (float) config('services.anthropic.cost_per_mtok_input'),
            'output' => (float) config('services.anthropic.cost_per_mtok_output'),
            'cache_read' => (float) config('services.anthropic.cost_per_mtok_cache_read'),
            'cache_write' => (float) config('services.anthropic.cost_per_mtok_cache_write'),
        ];

        $cost = 0.0;

        foreach ($counts as $key => $tokens) {
            $cost += $tokens / 1_000_000 * $rates[$key];
        }

        return [...$counts, 'cost_usd' => round($cost, 6), 'model' => $model];
    }
}
