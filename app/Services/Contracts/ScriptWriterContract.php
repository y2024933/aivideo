<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Data\Llm\ScriptOutput;
use App\Models\Product;

/**
 * LLM 自動寫稿。真實實作 ClaudeScriptWriter，開發與測試走 StubScriptWriter。
 */
interface ScriptWriterContract
{
    /**
     * 鏡頭數不開放呼叫端指定：它是由 products.video_length_seconds 推導出來的
     * （見 ScriptPromptBuilder::videoSection），兩個來源會互相打架。
     *
     * @param  array{retry_report?: \App\Data\ComplianceReport, retry_feedback?: string}  $options
     *
     * @throws \RuntimeException API 失敗、逾時或回傳無法解析
     */
    public function write(Product $product, array $options = []): ScriptOutput;

    /**
     * 上一次 write() 的 token 用量與實算成本。
     *
     * @return array{input: int, output: int, cache_read: int, cache_write: int, cost_usd: float, model: string}
     */
    public function lastUsage(): array;
}
