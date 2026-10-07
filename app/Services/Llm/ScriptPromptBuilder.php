<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Data\ComplianceFinding;
use App\Data\ComplianceReport;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Compliance\AdComplianceChecker;
use App\Support\MandarinNumber;

/**
 * 寫稿 prompt 組裝。
 *
 * system 固定切成三個 content block，cache 斷點放在最後一個：
 *
 *   1. ROLE_AND_STYLE —— 角色與短影音結構鐵則（幾乎不會改）
 *   2. zhTwRules()    —— 繁中規範，大陸用語對照表直接從 resources/data/compliance 讀
 *   3. complianceRules() —— 該 profile 的完整法規紅線（最長、最常被改）
 *
 * ⚠️ Anthropic prompt cache 的最小單位是 1,024 token，低於門檻的 cache_control 會被
 * 直接忽略（不報錯，只是永遠 miss）。block 3 刻意把禁用詞列全，讓 system 穩定超過門檻；
 * 每支影片重跑一次就省下約 90% 的 input 成本，所以「長」在這裡是功能而不是浪費。
 *
 * ⚠️ cache 斷點之前的內容必須逐位元組一致才會命中，因此 block 1~3 不可以塞商品資料、
 * 時間戳或隨機字串 —— 商品相關的一切都只能進 user prompt。
 */
final class ScriptPromptBuilder
{
    /** 描述節錄上限：蝦皮商品描述動輒 5,000 字，整段餵進去只會稀釋重點並多花錢 */
    private const DESCRIPTION_LIMIT = 800;

    /** 每鏡平均秒數，用來把目標影片長度換算成建議鏡頭數 */
    private const SECONDS_PER_SHOT = 5.0;

    private const ROLE_AND_STYLE = <<<'TXT'
# 你的角色

你是台灣蝦皮分潤（聯盟行銷）短影音的專職編劇，每天寫十幾支 30 秒直式短影音的腳本。
你的稿子會被直接丟進自動化流程渲染成影片並發佈，沒有人會幫你潤稿，所以每一個字都必須可以直接上線。

你的觀眾是在蝦皮影音與 IG Reels 滑手機的台灣人。他們的手指停留時間是 1.5 秒：
開場沒抓住就滑掉，中段說教就滑掉，結尾不給理由就不會點連結。

# 結構鐵則

依序輸出下列角色的鏡頭，不可跳過 hook、pain 與 cta：

1. hook（1.5–2.5 秒）
   用「反差」「疑問」或「具體數字」切入，第一句就要讓人覺得「這在講我」。
   ⛔ 嚴格禁止開場用「大家好」「哈囉大家」「今天要介紹」「今天要跟大家分享」「不囉嗦」
      這類自我介紹或節目開場語 —— 這是最常見的滑走原因。
   ✅ 可用句型：「每天通勤最痛的其實不是人多」「一條線用三個月就斷，你也遇過嗎」「八小時，這是我實測的數字」

2. pain（2–3.5 秒）
   把觀眾現在的困擾講具體：場景 + 感受。不要寫形容詞堆疊，寫畫面。

3. feature（每鏡 2.5–4 秒，共 1~3 鏡）
   一鏡只講一個賣點，講完就切。賣點必須來自 user prompt 提供的商品資料，
   不得自行加碼、換算或美化規格數字（規格只能照抄賣場頁面的寫法）。

4. proof（2–3 秒，選用）
   ⛔ 只有在 user prompt 明確給了評分、評價數或已售出數量時才可以寫這一鏡。
      沒有這些數字就「不要」產生 proof 鏡頭。
   ⛔ 絕對不可以編造評價內容、使用者心得、媒體報導或得獎紀錄。
   ✅ 允許的寫法是照抄事實：「4.8 顆星、2,300 則評價」。

5. cta（2–3 秒）
   給一個現在就點的理由，但不可用「限時」「最後一天」「僅剩」等無法驗證的急迫話術。
   ✅ 「想試的我放連結在下面」「規格我整理在貼文裡」

# 字幕規則

- 每鏡字幕 **最多 22 個中文字**（含標點）。這是硬性限制：超過會在 1080x1920 的畫面上折成
  兩三行蓋住商品。**當一句話講不完時，請拆成兩個分鏡**，不要硬塞進同一鏡。
  例：「支援單次充電可用 9 小時，搭配充電盒總續航 36 小時」（28 字）應拆成
  「單次充電聽 9 小時」（9 字）+「充電盒帶著總共 36 小時」（11 字）兩鏡。
- 字幕是「畫面上的字」，不可以出現 hashtag、# 符號、emoji、顏文字、網址、@ 帳號。
- 不要在字幕裡寫「第一鏡」「鏡頭一」這種製作標記。
- 配音稿（voiceoverText）可以比字幕口語、略長一點，但語意必須一致；
  影片設定為「無配音」時，voiceoverText 一律回空字串。

# 選圖規則（imageRef）

- imageRef 是 user prompt「可用商品圖」清單的索引（0-based），只能用清單裡有的索引。
- 每一鏡都要選「最能表現這一鏡在講的事」的那張圖，不要全部都用同一張。
- 主圖（通常是索引 0）資訊最乾淨，一般留給 hook。
- 細節圖、情境圖、規格圖優先配給對應的 feature 鏡。

# 轉場與運鏡

- 節奏快的段落用 cut，情緒轉折用 crossfade，其餘可用 slideLeft / slideUp / wipeLeft / clockWipe。
- 運鏡（kenBurns）不要連續兩鏡用同一種，長圖適合 panUp / panDown，商品特寫適合 zoomIn。

# 貼文文案（caption）

- 180 字以內，像在跟朋友講話，不要寫成官方新聞稿。
- ⛔ 不要寫利益揭露句，系統會自動把揭露前綴放在第一行。
- ⛔ 不要在 caption 裡放 hashtag，hashtag 一律放在 hashtags 欄位（不含 # 符號）。
TXT;

    /** @var array<string, string>|null 大陸用語 => 台灣用語 */
    private static ?array $mainlandTerms = null;

    public function __construct(private readonly AdComplianceChecker $checker) {}

    /**
     * system prompt 的三個 content block，cache 斷點在最後一個。
     *
     * @return list<array<string, mixed>>
     */
    public function system(string $profile): array
    {
        return [
            ['type' => 'text', 'text' => self::ROLE_AND_STYLE],
            ['type' => 'text', 'text' => $this->zhTwRules()],
            ['type' => 'text', 'text' => $this->complianceRules($profile), 'cache_control' => ['type' => 'ephemeral']],
        ];
    }

    /** block 2：繁中規範。對照表直接讀 resources/data/compliance/mainland_terms.php，不另抄一份。 */
    public function zhTwRules(): string
    {
        $pairs = [];

        foreach (self::mainlandTerms() as $term => $taiwan) {
            $pairs[] = $term.'→'.$taiwan;
        }

        return <<<TXT
# 語言規範：臺灣繁體中文

所有輸出（字幕、配音稿、caption、hashtags）只能使用臺灣繁體中文。

## 一、禁止簡體字

⛔ 不可出現任何簡體字。這是 blocking 等級的錯誤，整份稿會被退回重寫。
常見錯誤：发（應為 發 或 髮）、复（應為 復／複／覆）、台灣用「臺」或「台」皆可但不可用「台」的簡體異體寫法、
「里／裡」「只／隻」「干／乾／幹」要依語意選對字。

⚠️ 一簡對多繁時必須依語意選字，不可隨便挑一個：
- 头发 → 頭髮（不是「頭發」）
- 复原 → 復原；复杂 → 複雜；重复 → 重複
- 发现 → 發現；发型 → 髮型

## 二、禁止中國大陸用語（即使寫成繁體字也算）

以下是「大陸用語→台灣用語」對照表，左邊的詞一個都不能出現：

{$this->joinTerms($pairs)}

## 三、禁止大陸網路流行語

⛔ 不可使用：性價比（請用 CP 值）、親測、小白、種草、入股不虧、绝绝子、yyds、必入、閉眼入、
     家人們、沖鴨、打卡、安利、爆款、神器、真香、無腦買、剁手、秒殺。
這些詞會讓台灣觀眾立刻感覺「這不是台灣人寫的」，也是平台違規檢舉的常見理由。

## 四、標點與數字

- 標點用全形：，。、！？「」（）—— 不要用半形 , . ! ? 與英文引號 " '。
- 字幕不要用句尾句號，口語短句直接斷。
- 數字可用阿拉伯數字（8 小時、4.8 顆星），單位用台灣慣用寫法。
- 需要提到金額時（僅限 caption，字幕與配音稿不得出現具體金額或折扣數字），寫成 NT$ 加千分位，例：NT$1,290。
TXT;
    }

    /**
     * block 3：該 profile（含 extends 繼承鏈）的完整法規紅線。
     *
     * 每條規則輸出「禁止什麼 + 法規依據 + 罰則 + 建議替代詞」。禁用詞刻意列全，
     * 一方面 LLM 看得到完整黑名單才不會踩，一方面讓 system 穩定超過 1,024 token 的 cache 門檻。
     */
    public function complianceRules(string $profile): string
    {
        $rules = (array) config('compliance.rules', []);
        $resolved = $this->checker->resolveRules($profile);

        // blocking 先列：LLM 讀 prompt 也有「前面的權重比較高」的傾向
        usort($resolved, fn (string $a, string $b) => (int) (($rules[$b]['severity'] ?? '') === 'blocking') <=> (int) (($rules[$a]['severity'] ?? '') === 'blocking'));

        $blocked = (bool) config("compliance.profiles.{$profile}.blocked", false);
        $sections = [];
        $index = 0;

        foreach ($resolved as $ruleId) {
            $rule = $rules[$ruleId] ?? null;

            if ($rule === null || ($rule['check'] ?? null) === 'disclosure_prefix') {
                continue;   // 揭露前綴由系統自動加，不是 LLM 要寫的東西
            }

            $sections[] = $this->ruleSection(++$index, (string) $ruleId, $rule);
        }

        $header = <<<TXT
# 廣告法規紅線（合規類別：{$profile}，規則版本：{$this->checker->rulesVersion()}）

你寫的每一個字都受台灣廣告法規拘束。公平會《對於網路廣告案件之處理原則》第三點第二項
明文把「部落客、網紅、直播主」列為廣告主 —— 違規的是推廣者本人，不是品牌。
而食藥署《違規廣告行為數認定原則》以違規產品數認定行為數，模板裡寫死一個違規詞，
一百支影片就是一百個獨立違規行為。

下列規則一律適用於字幕、配音稿、caption 與 hashtags：

- ⛔ blocking：出現即整份稿退回重寫，沒有例外。
- ⚠️ warning：會卡住流程等人工逐條確認，請一律主動避開。

寫稿時的安全原則：
1. 講「我的使用感受」而不是「產品的效果」。主觀感受不構成效能宣稱。
2. 規格數字只照抄賣場頁面，不自行換算、推論或美化。
3. 沒有數據的比較與最高級用語全部刪掉。
4. 不確定能不能講的，就不要講 —— 少一個賣點不會怎樣，被罰錢會。
TXT;

        if ($blocked) {
            $header .= "\n\n⛔ 注意：此分類依法不開放製作影片。若收到這個 profile，請在 caption 說明無法製作的原因。";
        }

        return $header."\n\n".implode("\n\n", $sections);
    }

    /**
     * user prompt：商品資料 + 可用圖片 + 影片設定（+ 重試時的違規清單）。
     *
     * @param  ComplianceReport|null  $retry  上一次輸出的合規報告
     * @param  string|null  $zhTwFeedback  TraditionalChineseValidator::buildRetryFeedback() 的字級修正清單
     */
    public function user(Product $product, ?ComplianceReport $retry = null, ?string $zhTwFeedback = null): string
    {
        $sections = [
            $this->productSection($product),
            $this->imageSection($product),
            $this->videoSection($product),
        ];

        if ($retry !== null || filled($zhTwFeedback)) {
            $sections[] = $this->retrySection($retry, $zhTwFeedback);
        }

        return implode("\n\n", $sections);
    }

    private function productSection(Product $product): string
    {
        $title = (string) $product->title;

        if ($product->title_has_simplified) {
            $title .= '（原標題含簡體字，字幕請改用繁體等義詞，不要照抄）';
        }

        $lines = ['# 商品資料', '', "- 標題：{$title}"];

        foreach ([
            '品牌' => $product->brand,
            '分類' => $product->category ?: implode(' > ', (array) ($product->category_path ?? [])),
        ] as $label => $value) {
            if (filled($value)) {
                $lines[] = "- {$label}：{$value}";
            }
        }

        if (filled($product->price)) {
            $price = MandarinNumber::formatPrice((float) $product->price);
            $lines[] = filled($product->price_before_discount) && (float) $product->price_before_discount > (float) $product->price
                ? "- 售價：{$price}（原價 ".MandarinNumber::formatPrice((float) $product->price_before_discount).'）'
                : "- 售價：{$price}";
        }

        // 評分與銷量是 proof 鏡唯一的合法素材；沒有就明講「不要寫 proof」，否則 LLM 會自己編
        $hasProof = filled($product->rating_star) || filled($product->historical_sold);

        if (filled($product->rating_star)) {
            $lines[] = "- 評分：{$product->rating_star} 顆星".(filled($product->rating_count) ? "（{$product->rating_count} 則評價）" : '');
        }

        if (filled($product->historical_sold)) {
            $lines[] = "- 已售出：{$product->historical_sold} 件";
        }

        if (! $hasProof) {
            $lines[] = '- 評分與銷量：賣場未提供 → ⛔ 不要產生 proof 鏡頭，也不可自行編造評價或銷量';
        }

        if ($specs = $this->specs($product)) {
            $lines[] = "- 規格摘要：{$specs}";
        }

        if (filled($product->description)) {
            $lines[] = '';
            $lines[] = '## 商品描述節錄（只能從這裡取賣點，不可自行加碼）';
            $lines[] = '';
            $lines[] = mb_substr(trim((string) $product->description), 0, self::DESCRIPTION_LIMIT);
        }

        return implode("\n", $lines);
    }

    private function imageSection(Product $product): string
    {
        $images = $product->selectedImages()->get();
        $lines = ['# 可用商品圖（imageRef 只能用下列索引）', ''];

        if ($images->isEmpty()) {
            return implode("\n", [...$lines, '（無可用圖片，imageRef 一律填 0）']);
        }

        foreach ($images->values() as $index => $image) {
            /** @var ProductImage $image */
            $lines[] = sprintf(
                '%d: %s %dx%d%s',
                $index,
                $image->is_primary ? '主圖' : '商品圖',
                (int) $image->width,
                (int) $image->height,
                ($image->aspectRatio() ?? 1.0) >= 1.5 ? '（直式長圖，適合 panUp／panDown）' : '',
            );
        }

        return implode("\n", $lines);
    }

    private function videoSection(Product $product): string
    {
        $seconds = (int) ($product->video_length_seconds ?: 30);
        $shots = max(4, min(8, (int) round($seconds / self::SECONDS_PER_SHOT)));

        $audio = match ((string) $product->audio_mode) {
            'tts' => 'AI 配音（voiceoverText 必填，寫成可以直接唸出來的口語句子）',
            'bgm_only' => '只有背景音樂，無人聲（voiceoverText 一律回空字串）',
            default => '無配音（voiceoverText 一律回空字串）',
        };

        return implode("\n", [
            '# 影片設定',
            '',
            "- 目標長度：{$seconds} 秒（所有 durationSeconds 加起來請落在這個數字附近）",
            "- 建議鏡頭數：{$shots} 個",
            "- 音訊模式：{$audio}",
            '- 直式 9:16，字幕一律靠畫面下方三分之一，請以此估算字數。',
        ]);
    }

    private function retrySection(?ComplianceReport $retry, ?string $zhTwFeedback): string
    {
        $lines = [
            '# ⚠️ 你上一次的輸出違反規則，請修正後重新輸出完整腳本',
            '',
            '不要只回修正的那幾鏡，要重新輸出完整的 shots / caption / hashtags。',
            '修正時保持原本的節奏與鏡頭數，只改掉違規的字。',
            '',
        ];

        foreach ($retry?->blocking() ?? [] as $finding) {
            $lines[] = $this->findingLine('⛔', $finding);
        }

        foreach ($retry?->unacknowledgedWarnings() ?? [] as $finding) {
            $lines[] = $this->findingLine('⚠️', $finding);
        }

        if ($retry?->profileBlocked) {
            $lines[] = '⛔ 此商品分類不開放製作影片：'.$retry->profileBlockedReason;
        }

        if (filled($zhTwFeedback)) {
            $lines[] = '';
            $lines[] = '## 繁中逐字修正';
            $lines[] = '';
            $lines[] = trim((string) $zhTwFeedback);
        }

        return implode("\n", $lines);
    }

    private function findingLine(string $icon, ComplianceFinding $finding): string
    {
        $line = sprintf('%s %s 的「%s」：%s', $icon, $finding->field, $finding->matched, $finding->message);

        if (filled($finding->law)) {
            $line .= "（依據：{$finding->law}）";
        }

        if (filled($finding->suggestion)) {
            $line .= " 建議改法：{$finding->suggestion}";
        }

        return $line;
    }

    /** @param array<string, mixed> $rule */
    private function ruleSection(int $index, string $ruleId, array $rule): string
    {
        $severity = (string) ($rule['severity'] ?? 'warning');
        $icon = $severity === 'blocking' ? '⛔ blocking' : '⚠️ warning';
        $lines = ["## {$index}. {$ruleId} {$icon}", '', '- 規則：'.(string) ($rule['message'] ?? '')];

        if (filled($rule['law'] ?? null)) {
            $lines[] = '- 法規依據：'.(string) $rule['law'];
        }

        if (filled($rule['penalty'] ?? null)) {
            $lines[] = '- 罰則：'.(string) $rule['penalty'];
        }

        if ($words = (array) ($rule['words'] ?? [])) {
            $lines[] = '- 禁用詞（含這些字串即違規）：'.$this->joinTerms(array_map(strval(...), $words));
        }

        if ($soft = (array) ($rule['soft_words'] ?? [])) {
            $lines[] = '- 需格外小心的詞（與療效動詞同時出現即升為 blocking）：'.$this->joinTerms(array_map(strval(...), $soft));
        }

        if ($allowed = (array) ($rule['context_whitelist'] ?? [])) {
            $lines[] = '- 官方允許用語（可以安心使用）：'.$this->joinTerms(array_map(strval(...), $allowed));
        }

        if (filled($rule['suggestion'] ?? null)) {
            $lines[] = '- 建議替代寫法：'.(string) $rule['suggestion'];
        }

        if (match ((string) ($rule['check'] ?? '')) {
            'simplified_chars', 'mainland_terms', 'banned_phrases', 'glyph_variants' => true,
            default => false,
        }) {
            $lines[] = '- 詳細清單見前一段「語言規範：臺灣繁體中文」。';
        }

        return implode("\n", $lines);
    }

    /** @param list<string> $terms */
    private function joinTerms(array $terms): string
    {
        return implode('、', $terms);
    }

    private function specs(Product $product): string
    {
        $parts = [];

        foreach ((array) ($product->variations ?? []) as $key => $value) {
            if (is_array($value)) {
                $name = (string) ($value['name'] ?? $key);
                $options = implode('／', array_map(strval(...), (array) ($value['options'] ?? array_filter($value, is_scalar(...)))));
                $parts[] = trim("{$name}：{$options}", '：');

                continue;
            }

            $parts[] = is_int($key) ? (string) $value : "{$key}：{$value}";
        }

        return implode('；', array_filter($parts));
    }

    /** @return array<string, string> */
    private static function mainlandTerms(): array
    {
        return self::$mainlandTerms ??= (array) require rtrim((string) config('compliance.data_path', resource_path('data/compliance')), '/').'/mainland_terms.php';
    }
}
