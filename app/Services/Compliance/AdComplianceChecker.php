<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Data\ComplianceFinding;
use App\Data\ComplianceReport;
use Carbon\CarbonImmutable;

/**
 * 廣告合規守門員。
 *
 * 規則與法條全部來自 config/compliance.php，本類別只負責「怎麼掃」：
 * profile 繼承展開、白名單遮罩、soft_words 升級、downgrade、acknowledged 合併。
 */
final class AdComplianceChecker
{
    /** 單一規則在單一欄位最多回報幾筆。forbidden_category 的 pattern 是 /./u，不設上限會噴出整篇文章的字數 */
    private const MAX_FINDINGS_PER_RULE_FIELD = 20;

    /** 詞表轉 regex 時每批的詞數上限，避免單一 pattern 過長 */
    private const WORDS_PER_BATCH = 150;

    public function __construct(private readonly TraditionalChineseValidator $zhTw) {}

    /**
     * @param  array<string, string>  $fields  欄位路徑 => 文字
     * @param  array<string, mixed>  $context  例：['is_ai_generated' => true]
     */
    public function check(array $fields, string $profile = 'general', array $context = []): ComplianceReport
    {
        $profiles = (array) config('compliance.profiles', []);
        $profile = isset($profiles[$profile]) ? $profile : 'general';
        $rules = (array) config('compliance.rules', []);

        [$blocked, $blockedReason] = $this->blockedState($profile);

        $raw = [];

        foreach ($this->resolveRules($profile) as $ruleId) {
            $rule = $rules[$ruleId] ?? null;

            if ($rule === null) {
                continue;
            }

            // only_when：fake_testimonial 只在 AI 生成模式下才適用（真人口述薦證是合法的）
            if (isset($rule['only_when']) && ($context[$rule['only_when']] ?? null) !== true) {
                continue;
            }

            if (($rule['check'] ?? null) === 'disclosure_prefix') {
                array_push($raw, ...$this->checkDisclosure($ruleId, $rule, $fields, $context));

                continue;
            }

            foreach ($fields as $field => $text) {
                array_push($raw, ...$this->runRule($ruleId, $rule, (string) $field, (string) $text));
            }
        }

        $raw = $this->upgradeSoftWords($raw);

        return new ComplianceReport(
            profile: $profile,
            rulesVersion: $this->rulesVersion(),
            rulesFingerprint: $this->rulesFingerprint(),
            findings: array_map(fn (array $row) => $this->toFinding($row), $raw),
            checkedAt: CarbonImmutable::now(),
            profileBlocked: $blocked,
            profileBlockedReason: $blockedReason,
        );
    }

    /**
     * 依商品分類推導 profile。
     * 'restricted' 最優先（命中即整類不給做），其次長字串優先（保健食品 > 食品）。
     *
     * @param  list<string>|null  $categoryPath  蝦皮分類階層，例：['美妝保健', '保健食品']
     */
    public function profileForCategory(?string $category, ?array $categoryPath = null): string
    {
        $haystack = trim(implode(' ', array_filter([$category, ...($categoryPath ?? [])])));

        if ($haystack === '') {
            return 'general';
        }

        $map = (array) config('compliance.category_map', []);
        $keys = array_map(strval(...), array_keys($map));
        usort($keys, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ([true, false] as $restrictedPass) {
            foreach ($keys as $key) {
                if (($map[$key] === 'restricted') === $restrictedPass && mb_strpos($haystack, $key) !== false) {
                    return (string) $map[$key];
                }
            }
        }

        return 'general';
    }

    public function rulesVersion(): string
    {
        return (string) config('compliance.version', 'unversioned');
    }

    /** 規則內容的指紋。version 會忘記遞增，這個不會。 */
    public function rulesFingerprint(): string
    {
        $payload = json_encode(config('compliance.rules'), JSON_UNESCAPED_UNICODE)
            .json_encode(config('compliance.profiles'), JSON_UNESCAPED_UNICODE)
            .(string) config('compliance.disclosure_prefix');

        return substr(sha1($payload), 0, 12);
    }

    /**
     * 展開 extends 繼承鏈（supplement => food => general），父規則在前、去重保順序。
     *
     * @return list<string>
     */
    public function resolveRules(string $profile): array
    {
        $rules = [];

        foreach ($this->profileChain($profile) as $name) {
            foreach ((array) (config("compliance.profiles.{$name}.rules") ?? []) as $ruleId) {
                $rules[(string) $ruleId] = true;
            }
        }

        return array_keys($rules);
    }

    /**
     * 把舊報告的 acknowledged 狀態搬到新報告上。
     *
     * 保留條件：ruleId + field + matched 三者全同。以下任一情況強制清除：
     *
     *   1. severity 從 warning 升為 blocking —— 法規變嚴時，絕不能讓三個月前按過的
     *      「已確認無誤」讓一個現在已是 blocking 的違規悄悄通過。當初確認的前提已經不存在。
     *   2. 該規則的 law 欄位變更 —— 法條依據已不同，等於是另一件事，必須重新確認。
     *   3. matched 文字不同 —— operator 改過稿，命中的根本不是同一段文字。
     *
     * 另外 blocking 的 finding 永遠不可 acknowledged（即使舊報告說 true）。
     *
     * @param  array<string, mixed>|null  $oldReportArray  ComplianceReport::toArray() 或裸的 findings 陣列
     */
    public function mergeAcknowledged(ComplianceReport $new, ?array $oldReportArray): ComplianceReport
    {
        $old = [];

        foreach ((array) ($oldReportArray['findings'] ?? $oldReportArray ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $old[$this->ackKey((string) ($row['ruleId'] ?? ''), (string) ($row['field'] ?? ''), (string) ($row['matched'] ?? ''))] = $row;
        }

        $merged = array_map(function (ComplianceFinding $finding) use ($old) {
            $previous = $old[$this->ackKey($finding->ruleId, $finding->field, $finding->matched)] ?? null;

            $acknowledged = $finding->severity !== 'blocking'
                && (bool) ($previous['acknowledged'] ?? false)
                && ($previous['severity'] ?? null) === $finding->severity
                && ($previous['law'] ?? null) === $finding->law;

            return new ComplianceFinding(
                field: $finding->field,
                offset: $finding->offset,
                length: $finding->length,
                matched: $finding->matched,
                ruleId: $finding->ruleId,
                severity: $finding->severity,
                category: $finding->category,
                law: $finding->law,
                message: $finding->message,
                suggestion: $finding->suggestion,
                suggestions: $finding->suggestions,
                confidence: $finding->confidence,
                acknowledged: $acknowledged,
            );
        }, $new->findings);

        return new ComplianceReport(
            profile: $new->profile,
            rulesVersion: $new->rulesVersion,
            rulesFingerprint: $new->rulesFingerprint,
            findings: $merged,
            checkedAt: $new->checkedAt,
            profileBlocked: $new->profileBlocked,
            profileBlockedReason: $new->profileBlockedReason,
        );
    }

    private function ackKey(string $ruleId, string $field, string $matched): string
    {
        return $ruleId."\0".$field."\0".$matched;
    }

    /** @return list<string> general => ... => $profile（父在前） */
    private function profileChain(string $profile): array
    {
        $chain = [];
        $seen = [];
        $current = $profile;

        while ($current !== '' && ! isset($seen[$current]) && config("compliance.profiles.{$current}") !== null) {
            $seen[$current] = true;
            array_unshift($chain, $current);
            $current = (string) (config("compliance.profiles.{$current}.extends") ?? '');
        }

        return $chain;
    }

    /** @return array{0: bool, 1: string|null} 繼承鏈上任一層被擋就算擋，取最具體的那層的理由 */
    private function blockedState(string $profile): array
    {
        foreach (array_reverse($this->profileChain($profile)) as $name) {
            if ((bool) config("compliance.profiles.{$name}.blocked", false)) {
                return [true, (string) config("compliance.profiles.{$name}.blocked_reason", '此分類不開放製作影片')];
            }
        }

        return [false, null];
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return list<array<string, mixed>>
     */
    private function runRule(string $ruleId, array $rule, string $field, string $text): array
    {
        if ($text === '') {
            return [];
        }

        $findings = match ($rule['check'] ?? null) {
            'simplified_chars', 'mainland_terms', 'banned_phrases', 'glyph_variants' => $this->runNamedCheck((string) $rule['check'], $ruleId, $rule, $field, $text),
            default => $this->runWordsAndPatterns($ruleId, $rule, $field, $text),
        };

        // downgrade_if_matches：有合法許可證字號（衛部健食字第 A00123 號）時整條降為 warning 送人工核
        if (isset($rule['downgrade_if_matches']) && preg_match((string) $rule['downgrade_if_matches'], $text) === 1) {
            $findings = array_map(fn (array $row) => array_replace($row, ['severity' => 'warning']), $findings);
        }

        return array_slice($findings, 0, self::MAX_FINDINGS_PER_RULE_FIELD);
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return list<array<string, mixed>>
     */
    private function runWordsAndPatterns(string $ruleId, array $rule, string $field, string $text): array
    {
        // context_whitelist：「舒緩肌膚乾燥」是化粧品官方允許詞，先遮罩再比對黑名單
        $scan = $this->maskPhrases($text, (array) ($rule['context_whitelist'] ?? []));
        $findings = [];

        foreach ($this->matchWords($scan, (array) ($rule['words'] ?? [])) as $hit) {
            $findings[] = $this->row($ruleId, $rule, $field, $text, $hit, (string) $rule['severity']);
        }

        // soft_words：單獨出現只記 warning，同欄位另有 medical_verb 命中時才升級（見 upgradeSoftWords）
        foreach ($this->matchWords($scan, (array) ($rule['soft_words'] ?? [])) as $hit) {
            $findings[] = ['soft' => true] + $this->row($ruleId, $rule, $field, $text, $hit, 'warning');
        }

        foreach ((array) ($rule['patterns'] ?? []) as $pattern) {
            foreach ($this->matchPattern($scan, (string) $pattern) as $hit) {
                $findings[] = $this->row($ruleId, $rule, $field, $text, $hit, (string) $rule['severity']);
            }
        }

        usort($findings, fn (array $a, array $b) => $a['offset'] <=> $b['offset']);

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return list<array<string, mixed>>
     */
    private function runNamedCheck(string $check, string $ruleId, array $rule, string $field, string $text): array
    {
        $findings = [];

        if ($check === 'simplified_chars') {
            foreach ($this->zhTw->findSimplifiedChars($text) as $f) {
                $findings[] = $this->row($ruleId, $rule, $field, $text, ['offset' => $f['offset'], 'length' => 1, 'matched' => $f['char']], (string) $rule['severity'], [
                    'suggestion' => $f['suggestions'][0] ?? null,
                    'suggestions' => $f['suggestions'],
                    'confidence' => $f['confidence'],
                ]);
            }
        }

        if ($check === 'mainland_terms') {
            foreach ($this->zhTw->findMainlandTerms($text) as $f) {
                $findings[] = $this->row($ruleId, $rule, $field, $text, ['offset' => $f['offset'], 'length' => $f['length'], 'matched' => $f['term']], (string) $rule['severity'], ['suggestion' => $f['suggestion']]);
            }
        }

        if ($check === 'banned_phrases') {
            foreach ($this->zhTw->findBannedPhrases($text) as $f) {
                $findings[] = $this->row($ruleId, $rule, $field, $text, ['offset' => $f['offset'], 'length' => $f['length'], 'matched' => $f['phrase']], (string) $rule['severity']);
            }
        }

        if ($check === 'glyph_variants') {
            foreach ($this->zhTw->findGlyphVariants($text) as $f) {
                $findings[] = $this->row($ruleId, $rule, $field, $text, ['offset' => $f['offset'], 'length' => 1, 'matched' => $f['char']], (string) $rule['severity'], ['suggestion' => $f['standard']]);
            }
        }

        return $findings;
    }

    /**
     * 揭露句必須在貼文第一行，不可埋在 hashtag 群裡。
     *
     * @param  array<string, mixed>  $rule
     * @param  array<string, string>  $fields
     * @return list<array<string, mixed>>
     */
    private function checkDisclosure(string $ruleId, array $rule, array $fields, array $context = []): array
    {
        if (! array_key_exists('caption', $fields)) {
            return [];
        }

        $caption = (string) $fields['caption'];
        // ⚠️ 必須用「這個商品實際使用的前綴」而不是 config 的全域預設。
        //    disclosure_prefix 在 Filament 是可逐商品自由編輯的必填欄位，而
        //    ScriptFields 組 caption 時用的也是 $product->disclosure_prefix。
        //    這裡若寫死 config，operator 只要改過前綴（哪怕只是調語氣），
        //    合規就永遠 blocking，而錯誤訊息是「貼文第一行必須有揭露句」——
        //    他看得到揭露句就在第一行，完全無法自救。
        $prefix = trim((string) ($context['disclosure_prefix'] ?? config('compliance.disclosure_prefix', '')));
        $ok = $prefix !== '' && str_starts_with(trim($caption), $prefix);

        if ($ok && (bool) config('compliance.disclosure_rules.must_be_first_line', true)) {
            $ok = str_starts_with(trim((string) (preg_split('/\R/u', $caption)[0] ?? '')), $prefix);
        }

        return $ok ? [] : [$this->row($ruleId, $rule, 'caption', $caption, ['offset' => 0, 'length' => 0, 'matched' => ''], (string) $rule['severity'], [
            'suggestion' => $prefix,
        ])];
    }

    /**
     * 把白名單短語遮罩成等長空白（1 字元 => 1 字元，保持 mb offset 對齊）。
     *
     * @param  list<string>  $phrases
     */
    private function maskPhrases(string $text, array $phrases): string
    {
        if ($phrases === []) {
            return $text;
        }

        $chars = mb_str_split($text);
        usort($phrases, fn ($a, $b) => mb_strlen((string) $b) <=> mb_strlen((string) $a));

        foreach ($phrases as $phrase) {
            $phrase = (string) $phrase;
            $length = mb_strlen($phrase);

            if ($length === 0) {
                continue;
            }

            for ($from = 0; ($at = mb_strpos($text, $phrase, $from)) !== false; $from = $at + $length) {
                array_splice($chars, $at, $length, array_fill(0, $length, ' '));
            }
        }

        return implode('', $chars);
    }

    /**
     * @param  list<string>  $words
     * @return list<array{offset: int, length: int, matched: string}>
     */
    private function matchWords(string $text, array $words): array
    {
        if ($words === []) {
            return [];
        }

        // 長詞優先：alternation 取第一個成功的分支，不排序的話「最低價」會被「最低」吃掉
        $words = array_values(array_unique(array_map(strval(...), $words)));
        usort($words, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        $hits = [];

        foreach (array_chunk($words, self::WORDS_PER_BATCH) as $batch) {
            $pattern = '/('.implode('|', array_map(fn (string $w) => preg_quote($w, '/'), $batch)).')/u';
            $hits = [...$hits, ...$this->matchPattern($text, $pattern)];
        }

        return $hits;
    }

    /** @return list<array{offset: int, length: int, matched: string}> */
    private function matchPattern(string $text, string $pattern): array
    {
        if (@preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $hits = [];

        foreach ($matches[0] as [$matched, $byteOffset]) {
            // PREG_OFFSET_CAPTURE 給的是 byte offset，前端高亮要的是 mb 字元位置
            $hits[] = ['offset' => mb_strlen(substr($text, 0, $byteOffset)), 'length' => mb_strlen($matched), 'matched' => $matched];
        }

        return $hits;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array{offset: int, length: int, matched: string}  $hit
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function row(string $ruleId, array $rule, string $field, string $text, array $hit, string $severity, array $extra = []): array
    {
        return $extra + [
            'field' => $field,
            'offset' => $hit['offset'],
            'length' => $hit['length'],
            // 從原文取回，避免拿到遮罩後的空白
            'matched' => $hit['length'] > 0 ? mb_substr($text, $hit['offset'], $hit['length']) : $hit['matched'],
            'ruleId' => $ruleId,
            'severity' => $severity,
            'category' => (string) ($rule['category'] ?? 'general'),
            'law' => (string) ($rule['law'] ?? ''),
            'message' => (string) ($rule['message'] ?? ''),
            'suggestion' => isset($rule['suggestion']) ? (string) $rule['suggestion'] : null,
            'suggestions' => [],
            'confidence' => 'high',
            'soft' => false,
        ];
    }

    /**
     * soft_words 升級：同一欄位若也命中 medical_verb，疾病名就不是「冬天不怕感冒」而是
     * 「預防感冒」的醫療效能宣稱，升為 blocking。
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function upgradeSoftWords(array $rows): array
    {
        $medicalFields = [];

        foreach ($rows as $row) {
            if ($row['ruleId'] === 'medical_verb') {
                $medicalFields[$row['field']] = true;
            }
        }

        return array_map(function (array $row) use ($medicalFields) {
            if (($row['soft'] ?? false) === true && isset($medicalFields[$row['field']])) {
                $row['severity'] = 'blocking';
            }

            return $row;
        }, $rows);
    }

    /** @param array<string, mixed> $row */
    private function toFinding(array $row): ComplianceFinding
    {
        return new ComplianceFinding(
            field: (string) $row['field'],
            offset: (int) $row['offset'],
            length: (int) $row['length'],
            matched: (string) $row['matched'],
            ruleId: (string) $row['ruleId'],
            severity: (string) $row['severity'],
            category: (string) $row['category'],
            law: (string) $row['law'],
            message: (string) $row['message'],
            suggestion: $row['suggestion'] ?? null,
            suggestions: (array) ($row['suggestions'] ?? []),
            confidence: (string) ($row['confidence'] ?? 'high'),
        );
    }
}
