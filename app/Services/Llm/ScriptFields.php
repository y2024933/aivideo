<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Data\Llm\ScriptOutput;
use App\Models\Product;
use App\Models\Shot;

/**
 * 把腳本攤平成 AdComplianceChecker 吃的 `欄位路徑 => 文字`。
 *
 * 寫稿後（LLM 輸出）與 checkpoint ② 核准前（operator 可能手改過 DB）都要掃一次，
 * 兩邊必須是同一份組裝邏輯，否則會出現「生成時過、核准時莫名被擋」的鬼打牆。
 *
 * caption 刻意組成「揭露前綴 + 空行 + 貼文」：disclosure_required 規則檢查的是
 * 貼文第一行，而前綴是另一個欄位存的，不接起來掃永遠是 blocking。
 */
final class ScriptFields
{
    /** @return array<string, string> */
    public static function fromOutput(Product $product, ScriptOutput $output): array
    {
        $fields = [];

        foreach ($output->shots as $index => $shot) {
            $key = sprintf('S%02d', $index + 1);
            $fields["{$key}.subtitle"] = $shot->subtitle;
            $fields["{$key}.voiceover_text"] = $shot->voiceoverText;
        }

        return [...$fields, ...self::text($product, $output->caption, $output->hashtags)];
    }

    /** @return array<string, string> */
    public static function fromProduct(Product $product): array
    {
        $fields = [];

        foreach ($product->shots()->get() as $shot) {
            /** @var Shot $shot */
            $fields["{$shot->shot_id}.subtitle"] = (string) $shot->subtitle;
            $fields["{$shot->shot_id}.voiceover_text"] = (string) $shot->voiceover_text;
        }

        return [...$fields, ...self::text($product, (string) $product->caption, (array) ($product->hashtags ?? []))];
    }

    /**
     * @param  array<int, string>  $hashtags
     * @return array<string, string>
     */
    private static function text(Product $product, string $caption, array $hashtags): array
    {
        $prefix = trim((string) $product->disclosure_prefix);

        return [
            'caption' => trim($prefix === '' ? $caption : $prefix."\n\n".$caption),
            'hashtags' => implode(' ', array_map(fn ($tag) => '#'.ltrim((string) $tag, '#'), $hashtags)),
        ];
    }
}
