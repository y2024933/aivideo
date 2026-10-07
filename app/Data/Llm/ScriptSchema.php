<?php

declare(strict_types=1);

namespace App\Data\Llm;

use RuntimeException;

/**
 * 寫稿輸出 schema 的「provider 無關」單一事實來源。
 *
 * 為什麼需要這個檔案：ScriptOutput / ScriptShot 的 schema 原本只存在於 Anthropic SDK 的
 * #[Constrained] / #[Required] attribute 裡，等於跟 Anthropic 耦死。Gemini 吃的是
 * generationConfig.responseSchema（OpenAPI 3.0 / JSON Schema 的子集），格式完全不同。
 *
 * 因此 enum 值、數值界線與欄位說明全部集中在這裡：
 *   - ScriptShot / ScriptOutput 的 attribute 直接引用本類的常數（attribute 只吃常數運算式，
 *     `ScriptSchema::ROLES` 是合法的常數運算式）。
 *   - Gemini 走 toGeminiSchema()。
 * 兩邊取同一份值，schema 就不會漂移。tests/Unit/Llm/ScriptSchemaTest.php 守著這件事。
 */
final class ScriptSchema
{
    /** 與 App\Enums\ShotRole 一致（不含 other，LLM 不該產出「其他」） */
    public const ROLES = ['hook', 'pain', 'feature', 'proof', 'cta'];

    /** 與 App\Enums\KenBurns 一致（不含 auto，auto 是後端輪替用的，不給 LLM 選） */
    public const KEN_BURNS = ['zoomIn', 'zoomOut', 'panLeft', 'panRight', 'panUp', 'panDown', 'zoomInPanUp', 'zoomOutPanDown', 'none'];

    /** ProductResource::TRANSITIONS 的子集：只開短影音適合的 6 種，選項太多 LLM 會亂用 */
    public const TRANSITIONS = ['cut', 'crossfade', 'slideLeft', 'slideUp', 'wipeLeft', 'clockWipe'];

    public const MIN_SHOTS = 4;

    public const MAX_SHOTS = 8;

    public const MIN_HASHTAGS = 3;

    public const MAX_HASHTAGS = 8;

    public const MIN_DURATION = 1.5;

    public const MAX_DURATION = 6.0;

    /** ⚠️ 中文「字數」（mb_strlen）而不是位元組數 */
    public const MAX_SUBTITLE_CHARS = 22;

    public const MAX_CAPTION_CHARS = 180;

    public const DESCRIPTION = '蝦皮分潤短影音的分鏡腳本與貼文文案';

    // --- 欄位說明。數字刻意寫在字串裡（attribute 不能做 sprintf），改上面的常數時要一起改 ---

    public const DESC_ROLE = '鏡頭角色。hook=開場鉤子、pain=痛點、feature=賣點、proof=評價佐證（僅在有真實評價或銷量時才可使用）、cta=行動呼籲';

    public const DESC_IMAGE_REF = '要使用第幾張商品圖（0-based，對應 user prompt 裡「可用商品圖」的索引）。必須選最能表現這一鏡賣點的那張';

    public const DESC_KEN_BURNS = '靜態圖的運鏡方式。整支影片不要連續兩鏡用同一種';

    public const DESC_DURATION = '這一鏡的秒數，1.5 到 6.0 之間，0.5 秒為單位';

    public const DESC_SUBTITLE = '畫面字幕，臺灣繁體中文最多 22 個字（以中文字數計，不是位元組），口語短句，不可含 hashtag 與 emoji';

    public const DESC_VOICEOVER = '配音稿。可比字幕口語、略長；影片設定為「無配音」時一律回空字串';

    public const DESC_TRANSITION = '切到「下一鏡」的轉場。節奏快的段落用 cut';

    public const DESC_SHOTS = '4 到 8 個鏡頭，依序為 hook → pain → feature×1~3 →（proof）→ cta';

    public const DESC_CAPTION = '貼文內容，臺灣繁體中文 180 字以內。不要寫利益揭露句（系統會自動加在第一行），也不要放 hashtag';

    public const DESC_HASHTAGS = '3 到 8 個 hashtag，純文字不含 # 符號，臺灣繁體中文或英文';

    /**
     * 單一鏡頭的欄位定義。
     *
     * maxChars 是「中文字數」上限：Gemini 的 structured output 子集不支援 maxLength
     * （官方只列 enum / format 給 string），Anthropic SDK 的 maxLength 又是用 strlen
     * 算位元組 —— 兩邊都靠不住，所以字數只寫進 description 提示 LLM，真正的把關在
     * GenerateScriptJob::overlongSubtitles()。
     *
     * @return array<string, array{type: string, description: string, enum?: list<string>, min?: float|int, max?: float|int, maxChars?: int}>
     */
    public static function shotFields(): array
    {
        return [
            'role' => ['type' => 'string', 'description' => self::DESC_ROLE, 'enum' => self::ROLES],
            'imageRef' => ['type' => 'integer', 'description' => self::DESC_IMAGE_REF, 'min' => 0],
            'kenBurns' => ['type' => 'string', 'description' => self::DESC_KEN_BURNS, 'enum' => self::KEN_BURNS],
            'durationSeconds' => ['type' => 'number', 'description' => self::DESC_DURATION, 'min' => self::MIN_DURATION, 'max' => self::MAX_DURATION],
            'subtitle' => ['type' => 'string', 'description' => self::DESC_SUBTITLE, 'maxChars' => self::MAX_SUBTITLE_CHARS],
            'voiceoverText' => ['type' => 'string', 'description' => self::DESC_VOICEOVER],
            'transition' => ['type' => 'string', 'description' => self::DESC_TRANSITION, 'enum' => self::TRANSITIONS],
        ];
    }

    /**
     * 整份產出的欄位定義。
     *
     * @return array<string, array{type: string, description: string, min?: int, max?: int, maxChars?: int, itemType?: string}>
     */
    public static function outputFields(): array
    {
        return [
            'shots' => ['type' => 'array', 'description' => self::DESC_SHOTS, 'min' => self::MIN_SHOTS, 'max' => self::MAX_SHOTS],
            'caption' => ['type' => 'string', 'description' => self::DESC_CAPTION, 'maxChars' => self::MAX_CAPTION_CHARS],
            'hashtags' => ['type' => 'array', 'description' => self::DESC_HASHTAGS, 'min' => self::MIN_HASHTAGS, 'max' => self::MAX_HASHTAGS, 'itemType' => 'string'],
        ];
    }

    /**
     * Gemini 的 generationConfig.responseSchema。
     *
     * 型別用小寫（官方 structured outputs 文件列的是 string / number / integer /
     * boolean / object / array）；支援的關鍵字只有 properties、required、
     * additionalProperties、enum、format、minimum、maximum、items、minItems、maxItems。
     *
     * @return array<string, mixed>
     */
    public static function toGeminiSchema(): array
    {
        $shotFields = self::shotFields();
        $output = self::outputFields();

        return [
            'type' => 'object',
            'description' => self::DESCRIPTION,
            'properties' => [
                'shots' => [
                    ...self::geminiProperty($output['shots']),
                    'items' => [
                        'type' => 'object',
                        'properties' => array_map(self::geminiProperty(...), $shotFields),
                        'required' => array_keys($shotFields),
                    ],
                ],
                'caption' => self::geminiProperty($output['caption']),
                'hashtags' => [...self::geminiProperty($output['hashtags']), 'items' => ['type' => 'string']],
            ],
            'required' => array_keys($output),
        ];
    }

    /**
     * 把 provider 回傳的 assoc array 組成 ScriptOutput。
     *
     * 刻意寬鬆：Gemini 的 responseSchema 是「約束解碼」而不是硬保證，少一個欄位或回了
     * enum 外的值都有可能，整份退回重寫等於多付一次呼叫。能救的就給安全預設，
     * 真的沒救的（完全沒有鏡頭）才丟例外。
     *
     * @param  array<string, mixed>  $raw
     *
     * @throws RuntimeException shots 為空
     */
    public static function hydrate(array $raw): ScriptOutput
    {
        $shots = [];

        foreach (array_values((array) ($raw['shots'] ?? [])) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $shots[] = new ScriptShot(
                role: self::oneOf($row['role'] ?? null, self::ROLES, 'feature'),
                imageRef: max(0, (int) ($row['imageRef'] ?? 0)),
                kenBurns: self::oneOf($row['kenBurns'] ?? null, self::KEN_BURNS, 'zoomIn'),
                durationSeconds: self::clamp((float) ($row['durationSeconds'] ?? 3.0)),
                subtitle: trim((string) ($row['subtitle'] ?? '')),
                voiceoverText: trim((string) ($row['voiceoverText'] ?? '')),
                transition: self::oneOf($row['transition'] ?? null, self::TRANSITIONS, 'crossfade'),
            );
        }

        if ($shots === []) {
            throw new RuntimeException('LLM 回傳的腳本沒有任何鏡頭（shots 缺失或為空）');
        }

        $hashtags = array_values(array_filter(array_map(
            fn ($tag) => is_scalar($tag) ? ltrim(trim((string) $tag), '#') : '',
            (array) ($raw['hashtags'] ?? []),
        ), fn (string $tag) => $tag !== ''));

        return new ScriptOutput(
            shots: $shots,
            caption: trim((string) ($raw['caption'] ?? '')),
            hashtags: array_slice($hashtags, 0, self::MAX_HASHTAGS),
        );
    }

    /**
     * 單一欄位 → Gemini schema 片段。maxChars 刻意丟掉（見 shotFields() 註解）。
     *
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private static function geminiProperty(array $field): array
    {
        $property = ['type' => (string) $field['type'], 'description' => (string) $field['description']];

        if (isset($field['enum'])) {
            $property['enum'] = array_values((array) $field['enum']);
        }

        foreach ($field['type'] === 'array' ? ['min' => 'minItems', 'max' => 'maxItems'] : ['min' => 'minimum', 'max' => 'maximum'] as $key => $keyword) {
            if (isset($field[$key])) {
                $property[$keyword] = $field[$key];
            }
        }

        return $property;
    }

    /** @param list<string> $allowed */
    private static function oneOf(mixed $value, array $allowed, string $fallback): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $fallback;
    }

    private static function clamp(float $seconds): float
    {
        return max(self::MIN_DURATION, min(self::MAX_DURATION, $seconds));
    }
}
