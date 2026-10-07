<?php

declare(strict_types=1);

namespace App\Data\Llm;

use Anthropic\Lib\Attributes\Constrained;
use Anthropic\Lib\Concerns\StructuredOutputModelTrait;
use Anthropic\Lib\Contracts\StructuredOutputModel;

/**
 * 一次寫稿的完整產出：分鏡 + 貼文文案 + hashtag。
 *
 * caption 刻意「不含」揭露前綴與 hashtag：前綴由 products.disclosure_prefix 統一管理
 * （法規用語不該讓 LLM 每次重寫），hashtag 另成一欄才能在發佈時重組順序。
 *
 * ⚠️ 欄位說明與界線的事實來源是 ScriptSchema（Gemini 走 ScriptSchema::toGeminiSchema()）。
 */
final class ScriptOutput implements StructuredOutputModel
{
    use StructuredOutputModelTrait;

    /** @var list<ScriptShot> */
    #[Constrained(description: ScriptSchema::DESC_SHOTS, itemClass: ScriptShot::class, minItems: ScriptSchema::MIN_SHOTS)]
    public array $shots;

    #[Constrained(description: ScriptSchema::DESC_CAPTION, maxLength: ScriptSchema::MAX_CAPTION_CHARS)]
    public string $caption;

    /** @var list<string> */
    #[Constrained(description: ScriptSchema::DESC_HASHTAGS, minItems: ScriptSchema::MIN_HASHTAGS)]
    public array $hashtags;

    public static function description(): ?string
    {
        return ScriptSchema::DESCRIPTION;
    }

    /**
     * @param  list<ScriptShot>  $shots
     * @param  list<string>  $hashtags
     */
    public function __construct(array $shots = [], string $caption = '', array $hashtags = [])
    {
        $this->shots = $shots;
        $this->caption = $caption;
        $this->hashtags = $hashtags;
    }
}
