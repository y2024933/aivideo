<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Enums\ScriptProvider;
use App\Services\ClaudeScriptWriter;
use App\Services\Contracts\ScriptWriterContract;
use App\Services\GeminiScriptWriter;
use App\Services\Stubs\StubScriptWriter;

/**
 * 寫稿 provider 的解析點。
 *
 * 全域預設來自 config('services.script_provider')，商品層級可用
 * products.script_provider 覆寫（GenerateScriptJob 會帶進來）。
 */
final class ScriptWriterFactory
{
    public function make(?ScriptProvider $provider = null): ScriptWriterContract
    {
        // ⚠️ 防護網的一部分，不可繞過：use_real_apis 為 false 時連「指定了 provider」
        // 也一律回 stub，否則任何測試只要塞一個 script_provider 就會打真實 API。
        if (! config('services.use_real_apis')) {
            return new StubScriptWriter();
        }

        return match ($provider ?? $this->default()) {
            ScriptProvider::Gemini => app(GeminiScriptWriter::class),
            ScriptProvider::Claude => app(ClaudeScriptWriter::class),
            ScriptProvider::Stub => new StubScriptWriter(),
        };
    }

    public function default(): ScriptProvider
    {
        return ScriptProvider::tryFrom((string) config('services.script_provider')) ?? ScriptProvider::Gemini;
    }

    /**
     * 目前真的可用的 provider（key 有設）。給 Filament 下拉用 —— 列出沒 key 的選項
     * 只會讓 operator 選了之後卡在 script_failed。
     *
     * @return array<string, string> provider value => label
     */
    public function availableOptions(): array
    {
        return collect([
            [ScriptProvider::Gemini, config('services.gemini.api_key')],
            [ScriptProvider::Claude, config('services.anthropic.api_key')],
        ])->filter(fn (array $row) => filled($row[1]))
            ->mapWithKeys(fn (array $row) => [$row[0]->value => $row[0]->getLabel()])
            ->all();
    }
}
