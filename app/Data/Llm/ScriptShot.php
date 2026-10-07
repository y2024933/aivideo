<?php

declare(strict_types=1);

namespace App\Data\Llm;

use Anthropic\Core\Attributes\Required;
use Anthropic\Lib\Attributes\Constrained;
use Anthropic\Lib\Concerns\StructuredOutputModelTrait;
use Anthropic\Lib\Contracts\StructuredOutputModel;

/**
 * LLM 產出的單一鏡頭。
 *
 * 這是 Anthropic structured output 的 schema 定義，屬性名稱會原樣送進 JSON schema，
 * 因此刻意用 camelCase（與 DB 的 snake_case 欄位在 GenerateScriptJob 裡對映）。
 *
 * ⚠️ enum 值、數值界線與欄位說明的事實來源是 App\Data\Llm\ScriptSchema —— Gemini 與
 * Claude 兩邊的 schema 必須同值，所以這裡只做轉接，不自己抄一份常數。
 * tests/Unit/Llm/ScriptSchemaTest.php 與 ScriptPromptBuilderTest.php 守著這件事。
 */
final class ScriptShot implements StructuredOutputModel
{
    use StructuredOutputModelTrait;

    /** @see ScriptSchema::ROLES */
    public const ROLES = ScriptSchema::ROLES;

    /** @see ScriptSchema::KEN_BURNS */
    public const KEN_BURNS = ScriptSchema::KEN_BURNS;

    /** @see ScriptSchema::TRANSITIONS */
    public const TRANSITIONS = ScriptSchema::TRANSITIONS;

    #[Required(enum: self::ROLES)]
    #[Constrained(description: ScriptSchema::DESC_ROLE)]
    public string $role;

    #[Constrained(description: ScriptSchema::DESC_IMAGE_REF, minimum: 0)]
    public int $imageRef;

    #[Required(enum: self::KEN_BURNS)]
    #[Constrained(description: ScriptSchema::DESC_KEN_BURNS)]
    public string $kenBurns;

    #[Constrained(description: ScriptSchema::DESC_DURATION, minimum: ScriptSchema::MIN_DURATION, maximum: ScriptSchema::MAX_DURATION)]
    public float $durationSeconds;

    // ⚠️ SDK 的 maxLength 用 strlen 算位元組，中文一字 3 bytes 必定「超長」（只寫進
    // validation_warnings 不丟例外）。真正的字數把關在 GenerateScriptJob::overlongSubtitles()。
    #[Constrained(description: ScriptSchema::DESC_SUBTITLE, maxLength: ScriptSchema::MAX_SUBTITLE_CHARS)]
    public string $subtitle;

    #[Constrained(description: ScriptSchema::DESC_VOICEOVER)]
    public string $voiceoverText;

    #[Required(enum: self::TRANSITIONS)]
    #[Constrained(description: ScriptSchema::DESC_TRANSITION)]
    public string $transition;

    public function __construct(
        string $role = 'feature',
        int $imageRef = 0,
        string $kenBurns = 'zoomIn',
        float $durationSeconds = 3.0,
        string $subtitle = '',
        string $voiceoverText = '',
        string $transition = 'crossfade',
    ) {
        $this->role = $role;
        $this->imageRef = $imageRef;
        $this->kenBurns = $kenBurns;
        $this->durationSeconds = $durationSeconds;
        $this->subtitle = $subtitle;
        $this->voiceoverText = $voiceoverText;
        $this->transition = $transition;
    }
}
