<?php

declare(strict_types=1);

namespace App\Services\Compliance;

/**
 * 繁中守門員：簡體字、大陸用語、網路流行語、非台灣字形。
 *
 * 設計前提是「誤判比漏判更擾人」—— operator 每打一次「台北」被紅標，三天後
 * 就會把整個功能關掉。所以：
 *   1. 資料檔已先排除 203 個「同時是合法繁體」的字（台里表谷只后夫秋干云面系布…）
 *   2. 掃描前先把白名單（品牌名、英數型號）遮罩成等長空白
 *   3. 回傳的 offset 一律是 mb 字元位置，遮罩用「1 字元 => 1 個半形空白」保持索引對齊
 */
final class TraditionalChineseValidator
{
    /**
     * 邊界字：確實是簡體寫法，但在臺灣語境也可能是正字（么弟、云云），
     * 標 low 讓 UI 顯黃不顯紅。刻意寫在這裡而不是改資料檔。
     */
    private const LOW_CONFIDENCE_CHARS = ['么', '云', '尸', '甯', '丰', '叶', '朴', '干'];

    /**
     * glyph_variants.php 裡「技術上非台灣標準字形、但臺灣實務上通用且字型有 glyph」的字。
     * 「污」：教育部正字為「汙」，但「防污」「污染」在臺灣廣泛使用，報警告只會製造噪音。
     */
    private const GLYPH_VARIANT_IGNORE = ['污'];

    /** @var array<string, list<string>>|null */
    private static ?array $simplified = null;

    /** @var array<string, string>|null */
    private static ?array $glyphVariants = null;

    /** @var array<string, string>|null 已依詞長降冪排序 */
    private static ?array $mainlandTerms = null;

    /** @var list<string>|null 已依詞長降冪排序 */
    private static ?array $bannedPhrases = null;

    /**
     * 簡體字（字級）。
     *
     * @return list<array{char: string, offset: int, suggestions: list<string>, confidence: string}>
     */
    public function findSimplifiedChars(string $text): array
    {
        $map = self::simplified();
        $findings = [];

        foreach (mb_str_split($this->maskWhitelist($text)) as $offset => $char) {
            if (! isset($map[$char])) {
                continue;
            }

            $findings[] = [
                'char' => $char,
                'offset' => $offset,
                'suggestions' => array_values((array) $map[$char]),
                'confidence' => in_array($char, self::LOW_CONFIDENCE_CHARS, true) ? 'low' : 'high',
            ];
        }

        return $findings;
    }

    /**
     * 大陸用語（詞級，長詞優先，避免「筆記本電腦」被「筆記本」先吃掉）。
     *
     * @return list<array{term: string, offset: int, length: int, suggestion: string}>
     */
    public function findMainlandTerms(string $text): array
    {
        $masked = $this->maskWhitelist($text);
        $findings = [];
        $taken = [];

        foreach (self::mainlandTerms() as $term => $suggestion) {
            foreach ($this->occurrences($masked, (string) $term, $taken) as $offset) {
                $findings[] = ['term' => (string) $term, 'offset' => $offset, 'length' => mb_strlen((string) $term), 'suggestion' => (string) $suggestion];
            }
        }

        return $this->sortByOffset($findings);
    }

    /**
     * 應刪除的大陸網路流行語 / 違規比較用語。
     *
     * @return list<array{phrase: string, offset: int, length: int}>
     */
    public function findBannedPhrases(string $text): array
    {
        $masked = $this->maskWhitelist($text);
        $findings = [];
        $taken = [];

        foreach (self::bannedPhrases() as $phrase) {
            foreach ($this->occurrences($masked, $phrase, $taken) as $offset) {
                $findings[] = ['phrase' => $phrase, 'offset' => $offset, 'length' => mb_strlen($phrase)];
            }
        }

        return $this->sortByOffset($findings);
    }

    /**
     * 非台灣標準字形（没/户/着）。意思看得懂，但 Noto Sans TC 是繁體子集字型，
     * 可能缺 glyph 導致字幕渲染成方塊 □。
     *
     * @return list<array{char: string, offset: int, standard: string}>
     */
    public function findGlyphVariants(string $text): array
    {
        $map = self::glyphVariants();
        $findings = [];

        foreach (mb_str_split($this->maskWhitelist($text)) as $offset => $char) {
            $standard = $map[$char] ?? null;

            // $standard === $char：資料檔有兩筆自我對應（梁=>梁、麼=>麼），放過不然「什麼」會被誤報
            if ($standard === null || $standard === $char || in_array($char, self::GLYPH_VARIANT_IGNORE, true)) {
                continue;
            }

            $findings[] = ['char' => $char, 'offset' => $offset, 'standard' => $standard];
        }

        return $findings;
    }

    public function isClean(string $text): bool
    {
        return $this->findSimplifiedChars($text) === []
            && $this->findMainlandTerms($text) === []
            && $this->findBannedPhrases($text) === []
            && $this->findGlyphVariants($text) === [];
    }

    /**
     * ⚠️ 僅供 Filament「填入建議值」按鈕使用，絕不可在自動流程中呼叫。
     *
     * 一簡對多繁有歧義（发 => 發/髮、复 => 復/複/覆、荡 => 蕩/盪），本方法一律取
     * 第一候選，因此「头发」會被轉成「頭發」而不是「頭髮」。自動轉換必然出錯，
     * 只能給人工按下按鈕後自己檢查，正式流程請改走 LLM 重寫 + buildRetryFeedback()。
     */
    public function suggestTraditional(string $text): string
    {
        $chars = mb_str_split($text);

        foreach ($this->findSimplifiedChars($text) as $finding) {
            $chars[$finding['offset']] = $finding['suggestions'][0] ?? $chars[$finding['offset']];
        }

        foreach ($this->findGlyphVariants($text) as $finding) {
            $chars[$finding['offset']] = $finding['standard'];
        }

        return implode('', $chars);
    }

    /**
     * 把違規清單格式化成 LLM 重試用的 feedback 文字。
     *
     * @param  array<string, string>  $fieldFindings  欄位路徑 => 文字
     */
    public function buildRetryFeedback(array $fieldFindings): string
    {
        $lines = [];

        foreach ($fieldFindings as $field => $text) {
            $text = (string) $text;

            foreach ($this->findSimplifiedChars($text) as $f) {
                $lines[] = count($f['suggestions']) > 1
                    ? sprintf('- %s 第 %d 字「%s」是簡體字，候選為「%s」，請依語意選擇', $field, $f['offset'] + 1, $f['char'], implode('」或「', $f['suggestions']))
                    : sprintf('- %s 第 %d 字「%s」是簡體字，應為「%s」', $field, $f['offset'] + 1, $f['char'], $f['suggestions'][0] ?? '');
            }

            foreach ($this->findGlyphVariants($text) as $f) {
                $lines[] = sprintf('- %s 第 %d 字「%s」非台灣標準字形，應為「%s」', $field, $f['offset'] + 1, $f['char'], $f['standard']);
            }

            foreach ($this->findMainlandTerms($text) as $f) {
                $lines[] = sprintf('- %s 使用大陸用語「%s」，應改為「%s」', $field, $f['term'], $f['suggestion']);
            }

            foreach ($this->findBannedPhrases($text) as $f) {
                $lines[] = sprintf('- %s 含應刪除用語「%s」', $field, $f['phrase']);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * 把白名單（品牌正式名稱、英數型號）命中的段落換成等長的半形空白。
     * 「1 字元 => 1 字元」是刻意的：後續 mb_str_split 的索引才會跟原文對齊。
     */
    private function maskWhitelist(string $text): string
    {
        $chars = mb_str_split($text);

        foreach (array_keys((array) config('compliance.zh_tw_whitelist', [])) as $term) {
            $term = (string) $term;
            $length = mb_strlen($term);

            for ($from = 0; ($at = mb_strpos($text, $term, $from)) !== false; $from = $at + $length) {
                array_splice($chars, $at, $length, array_fill(0, $length, ' '));
            }
        }

        foreach ((array) config('compliance.zh_tw_whitelist_regex', []) as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE) === false) {
                continue;
            }

            foreach ($matches[0] as [$matched, $byteOffset]) {
                $length = mb_strlen($matched);
                array_splice($chars, mb_strlen(substr($text, 0, $byteOffset)), $length, array_fill(0, $length, ' '));
            }
        }

        return implode('', $chars);
    }

    /**
     * 找出 $needle 在 $haystack 的所有位置，跳過已被更長的詞佔走的區間。
     *
     * @param  array<int, bool>  $taken  by-ref，記錄已佔用的 mb 位置
     * @return list<int>
     */
    private function occurrences(string $haystack, string $needle, array &$taken): array
    {
        $length = mb_strlen($needle);

        if ($length === 0) {
            return [];
        }

        $offsets = [];

        for ($from = 0; ($at = mb_strpos($haystack, $needle, $from)) !== false; $from = $at + $length) {
            for ($i = $at; $i < $at + $length; $i++) {
                if (isset($taken[$i])) {
                    continue 2;
                }
            }

            for ($i = $at; $i < $at + $length; $i++) {
                $taken[$i] = true;
            }

            $offsets[] = $at;
        }

        return $offsets;
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @return list<array<string, mixed>>
     */
    private function sortByOffset(array $findings): array
    {
        usort($findings, fn (array $a, array $b) => $a['offset'] <=> $b['offset']);

        return $findings;
    }

    /** @return array<string, list<string>> */
    private static function simplified(): array
    {
        return self::$simplified ??= self::load('simplified_chars');
    }

    /** @return array<string, string> */
    private static function glyphVariants(): array
    {
        return self::$glyphVariants ??= self::load('glyph_variants');
    }

    /** @return array<string, string> */
    private static function mainlandTerms(): array
    {
        if (self::$mainlandTerms !== null) {
            return self::$mainlandTerms;
        }

        $terms = self::load('mainland_terms');
        uksort($terms, fn ($a, $b) => mb_strlen((string) $b) <=> mb_strlen((string) $a));

        return self::$mainlandTerms = $terms;
    }

    /** @return list<string> */
    private static function bannedPhrases(): array
    {
        if (self::$bannedPhrases !== null) {
            return self::$bannedPhrases;
        }

        $phrases = array_values(self::load('banned_phrases'));
        usort($phrases, fn ($a, $b) => mb_strlen((string) $b) <=> mb_strlen((string) $a));

        return self::$bannedPhrases = $phrases;
    }

    /** 86KB 的字表只在第一次用到時 require，之後吃 static 快取 */
    private static function load(string $file): array
    {
        return require rtrim((string) config('compliance.data_path', resource_path('data/compliance')), '/').'/'.$file.'.php';
    }
}
