# 建案影片 MD 標準 Schema v1

> **用途**：定義 Cowork（產腳本端）→ Laravel（執行端）的標準交換格式
> **格式**：YAML frontmatter + Markdown body
> **驗證**：可用 spatie/laravel-data DTO + JSON Schema

---

## 📐 整體結構

```markdown
---
[YAML frontmatter — 結構化資料，給 Laravel 解析用]
---

# [Markdown body — 人類可讀說明，給 Operator 看]
```

---

## 🔑 YAML Frontmatter 完整 Schema

### 必填欄位（Laravel 驗證用）

```yaml
# ===== 案件 metadata =====
schema_version: "1.0"            # schema 版本，Laravel 用來判斷如何解析
case_id: songzhu_dunfu_30s_v2    # 唯一識別，用作資料夾名 (a-z0-9_)
project_name: 松竹敦富             # 建案名
builder_name: 松竹建設             # 建商
video_length_seconds: 30         # 30 / 60 / 90 / 180
aspect_ratio: "9:16"             # 9:16 / 16:9 / 1:1

# ===== 角色 =====
character_dna: |
  a chubby and round Golden Retriever puppy with cute round face,
  ... (多行字串，包含完整角色描述)
character_nickname: 松松           # 中文暱稱

# ===== 場景鏡頭 =====
shots:                            # 至少 3 個鏡頭
  - id: S01
    duration_sec: 3
    use_model: ideogram_v2_turbo
    image_prompt: "..."
    kling_prompt: "..."
    voiceover: "..."
    subtitle: "..."

# ===== 配音 =====
voiceover_full: |
  完整旁白（全部串起來）

voice_id_preferred: zh-TW-HsiaoChenNeural

# ===== 合規 =====
compliance_watermark: "3D／AI 示意圖｜實品以建造完成後為準"
compliance_footer:
  - "坪數及總價以實際買賣契約為準"
  - "..."
```

### 選填欄位

```yaml
# ===== 客群定位 =====
location: 台中市北屯區
target_audience: first_buyer      # first_buyer / upgrade / retiree / investor
tone: warm_family                 # warm_family / premium / energetic / luxury
platforms: [FB, IG]               # 投放平台
area_range: "28-42 坪"
price_range: "1,500-2,400 萬"

# ===== 角色裝飾圖（S10/S11 用）=====
decorative_assets:
  - id: deco_a_welcome
    use_model: flux_kontext
    image_prompt: "..."
    kling_prompt: "..."
    placement: S10              # 要放在哪個 scene
    placement_position: right   # left / center / right / corner
  - id: deco_b_thumbsup
    ...

# ===== 品牌卡 / CTA 卡（S10/S11 渲染用）=====
brand_card:
  s10_main_text: "松竹敦富"
  s10_subtitle: "SongZhu DunFu"
  s10_slogan: "松松，陪你回家"

cta_card:
  cta_text: "立即預約賞屋"
  address: "[投放前填]"
  phone: "[投放前填]"
  qr_target_url: "[投放前填]"
  closing_voiceover: "松竹敦富，期待與您相遇。"

# ===== 鏡頭級別合規標註 =====
shot_disclaimers:
  S04: "公設示意圖｜以建照核准為準"
  S09: "松松推薦為品牌虛構代言人"

# ===== Reviewer 驗證資訊（從 Cowork 端傳來，用來判斷可不可以 import）=====
review:
  passed: true
  v2_score: 8.5
  red_flags_resolved: 10
  yellow_flags_resolved: 5
  reviewed_at: "2026-04-29"
  reviewers:
    - script: 7.5
    - prompt: 7.0
    - compliance: 9.0
    - brand: 7.5

# ===== BGM 建議（給 Remotion / CapCut 用）=====
bgm_keywords:
  - "heartwarming home"
  - "gentle piano family"

# ===== 衍生短版設計（同個 case 可一次匯入多個版本）=====
derivatives:
  - length: 15
    shots: [S01, S07, S09]      # 從主版本選哪幾個鏡頭
  - length: 60
    shots: [S01, S02, S03, S04, S05, S06, S07, S08, S09]
```

---

## 📋 每個鏡頭（shot）的完整 Schema

```yaml
shots:
  - id: S01                       # 必填，格式 ^S\d{2}$
    duration_sec: 3               # 必填，正數
    
    # 視覺生成
    use_model: ideogram_v2_turbo  # 必填，下方列舉
    image_prompt: "..."           # 必填，給 fal.ai
    image_settings:               # 選填，覆蓋 default
      aspect_ratio: "9:16"
      guidance_scale: 3.5
      num_inference_steps: 28
    
    # 動畫生成（選填，如果是靜圖鏡頭可省略）
    needs_animation: true         # 預設 true
    kling_prompt: "..."           # 給 fal.ai Kling
    kling_settings:
      duration: "5"               # Kling clip 秒數，"5" 或 "10"
      use_luma_instead: false     # true 改用 Luma
    
    # 配音與字幕
    voiceover: "..."              # 該鏡頭的旁白（與 voiceover_full 二選一）
    subtitle: "..."               # 字幕（可與 voiceover 不同）
    emotion: curious              # 情緒標記，輔助配音風格
    
    # 後製需求
    needs_post_text: true         # 是否需要 CapCut/Remotion 加文字
    post_text:
      content: "松松推薦"
      position: center            # 文字位置
      font: "思源黑體 Heavy"
      color: "#B22222"
      animation: "stamp_press"    # CapCut/Remotion preset
    
    needs_sound_effect: true      # 是否需要音效
    sound_effect: "stamp_press"   # 音效 ID
```

### `use_model` 列舉值

| 值 | 對應 fal.ai endpoint | 用途 |
|---|---|---|
| `flux_pro` | `fal-ai/flux-pro/v1.1` | 通用、無角色一致性需求 |
| `flux_kontext` | `fal-ai/flux-pro/kontext` | 帶角色參考圖（首選）|
| `flux_lora` | `fal-ai/flux-lora` | 訓練過 LoRA 的角色 |
| `ideogram_v2_turbo` | `fal-ai/ideogram/v2/turbo` | 中文渲染 |
| `recraft_v3` | `fal-ai/recraft-v3` | 中文備援 |

---

## 🔒 驗證規則（Laravel 端）

### 必填檢查
```php
// app/Data/CaseImportData.php
class CaseImportData extends Data {
    public function __construct(
        public string $schema_version,
        public string $case_id,           // ^[a-z0-9_]+$
        public string $project_name,
        public string $builder_name,
        public int $video_length_seconds,  // [15, 30, 60, 90, 180]
        public string $aspect_ratio,       // ['9:16', '16:9', '1:1']
        public string $character_dna,
        public string $character_nickname,
        public array $shots,               // min 3, max 11
        public string $voiceover_full,
        public string $voice_id_preferred,
        public string $compliance_watermark,
        public array $compliance_footer,
        // ...
    ) {}
}
```

### 業務規則檢查
```php
// 總時長 = 各鏡頭時長加總（容許 ±2 秒）
$totalDuration = array_sum(array_column($data->shots, 'duration_sec'));
abort_if(
    abs($totalDuration - $data->video_length_seconds) > 2,
    422,
    "shots 總時長 {$totalDuration}s 與宣告 {$data->video_length_seconds}s 差太多"
);

// 必須通過 Review 才能 import
abort_unless(
    $data->review['passed'] ?? false,
    422,
    "腳本未通過 Review，請回 Cowork 完成審查"
);

// shot id 不能重複
$shotIds = array_column($data->shots, 'id');
abort_if(
    count($shotIds) !== count(array_unique($shotIds)),
    422,
    "shot id 有重複"
);

// 中文渲染鏡頭必須用 ideogram 或 recraft
foreach ($data->shots as $shot) {
    if (str_contains($shot['image_prompt'], 'Chinese characters')
        && !in_array($shot['use_model'], ['ideogram_v2_turbo', 'recraft_v3'])) {
        throw new ValidationException("Shot {$shot['id']} 含中文渲染需求但未使用 Ideogram/Recraft");
    }
}
```

---

## 🧠 設計決策說明

### 為什麼 YAML 不是純 JSON？
- **Operator 看得懂**：YAML 比 JSON 更友善
- **Multi-line 字串**：用 `|` 寫長 prompt 不用一堆 `\n`
- **註解支援**：可以在 YAML 裡加 `#` 註解
- 但解析端：Laravel `symfony/yaml` 一行轉成 array

### 為什麼 frontmatter 而不是純 .yaml 檔？
- **MD body 給 Operator 看**：human-readable summary，看完才決定要不要匯入
- **單一檔案**：一個 .md 走遍 Cowork → Email → Laravel → Drive，不用同步多個檔案
- 標準格式：靜態網站世界（Hugo、Jekyll）證實 frontmatter 模式可靠

### 為什麼把 voiceover 同時放鏡頭級和全片級？
- **鏡頭級**：精確對齊每個動畫
- **全片級**：給 ElevenLabs / Azure 一次跑完最自然（中文連讀比一句句拼接好）
- Laravel 預設用全片級，鏡頭級當 fallback

### 為什麼 review 結果要寫進 frontmatter？
- **Laravel 拒絕未通過 review 的匯入**：強制使用 Cowork 流程
- **稽核痕跡**：哪天客戶問為什麼這樣寫，可以查
- 不可信任 operator 自己改 review.passed = true（這是 trust-but-verify，靠 Cowork 端產生的證據）

---

## 📦 範例：最小可用案件（minimum viable case）

如果 Operator 只想做極簡 15 秒案件，最小 MD 長這樣：

```markdown
---
schema_version: "1.0"
case_id: minimal_test_15s
project_name: 測試案件
builder_name: 測試建商
video_length_seconds: 15
aspect_ratio: "9:16"

character_dna: |
  a cute 3D Pixar style cat wearing a red sweater
character_nickname: 喵喵

shots:
  - id: S01
    duration_sec: 5
    use_model: flux_pro
    image_prompt: "a cute 3D Pixar cat sitting in a sunny living room"
    kling_prompt: "cat looks around curiously, gentle camera dolly in"
    voiceover: "歡迎回家"
    subtitle: "歡迎回家"
  - id: S02
    duration_sec: 5
    use_model: flux_pro
    image_prompt: "a cute 3D Pixar cat looking at the city view from a balcony"
    kling_prompt: "cat watches the city, gentle breeze ruffles fur"
    voiceover: "這就是你的家"
    subtitle: "這就是你的家"
  - id: S03
    duration_sec: 5
    use_model: flux_pro
    image_prompt: "a cute 3D Pixar cat with a satisfied smile"
    kling_prompt: "cat smiles contentedly, camera slowly zooms in"
    voiceover: "立即預約賞屋"
    subtitle: "立即預約賞屋"

voiceover_full: |
  歡迎回家。這就是你的家。立即預約賞屋。

voice_id_preferred: zh-TW-HsiaoChenNeural

compliance_watermark: "3D／AI 示意圖｜實品以建造完成後為準"
compliance_footer:
  - "坪數及總價以實際買賣契約為準"
  - "廣告核准字號：[投放前填]"

review:
  passed: true
  v2_score: 7.0
  reviewed_at: "2026-04-29"
---

# 測試案件 — 15 秒最小範例

[人類可讀的描述...]
```

---

## 🔄 版本演進策略

| Schema 版本 | 主要變化 | Laravel 處理 |
|---|---|---|
| 1.0 (現在) | 基礎結構 | 主分支支援 |
| 1.1 | 加入多語言字幕 | 向下相容 |
| 1.2 | 加入 LoRA 訓練資訊 | 向下相容 |
| 2.0 | 結構大改（如有需要）| 提供 migrator |

Laravel 端用 `schema_version` 欄位 dispatch 到對應的 parser：
```php
$parser = match ($data->schema_version) {
    '1.0' => new SchemaV1Parser(),
    '1.1' => new SchemaV1_1Parser(),
    default => throw new UnsupportedSchemaException(),
};
```

---

## 📍 檔案放在哪

```
~/Desktop/aivideo/09_腳本參考/
├── 14_MD_Schema.md             ← 這份（schema 定義）
├── 15_松竹敦富_30秒_標準格式.md  ← 實例（下一份）
└── ...

未來實際接案：
~/Desktop/aivideo/cases/
├── songzhu_dunfu_30s_v2.md     ← Cowork 產出的可匯入 MD
├── songzhu_dunfu_60s_v2.md
└── ...
```

