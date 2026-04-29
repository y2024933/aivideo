# 建案 AI 影片自動化 MVP — n8n 建置手冊

> **使用情境**：純內部使用（只有你一人操作）
> **MVP 範圍**：腳本生成 + 圖片批次 + 動畫批次 + 配音 → 輸出素材包
> **不在 MVP 內**：FFmpeg 自動剪接（CapCut 手動）、客戶 Form、付費收款
> **預估建置時間**：技術人員 25-35 小時

---

## 🎯 簡化後的架構（純內部用）

由於只有你一人操作，整個 pipeline 變成：

```
┌─────────────────────────────────────────┐
│  人工環節（保留判斷力）                   │
│                                          │
│  1. Cowork Artifact 角色設定器           │
│     → 拉選單 → 取得 Character DNA         │
│                                          │
│  2. Bing Image Creator                   │
│     → 產 Character Sheet 主視覺          │
│     → 你親眼挑選最符合的版本              │
│     → 上傳到 R2 / Google Drive           │
│                                          │
│  3. 開啟 n8n 內部 Form                   │
│     → 填建案資料 + DNA + 主視覺 URL       │
│     → 點 Submit                          │
└────────────┬────────────────────────────┘
             ↓
┌─────────────────────────────────────────┐
│  自動化環節（n8n 處理）                   │
│                                          │
│  Workflow A：腳本生成                     │
│  ├─ Claude API → 寫 v1 腳本              │
│  ├─ 4 Sub-workflows 並行：Review        │
│  ├─ Claude API → 產 v2 修正版            │
│  └─ 存腳本到 R2                          │
│                                          │
│  Workflow B：圖片批次（依賴 A）            │
│  ├─ Loop 9 鏡頭                          │
│  ├─ fal.ai Flux Pro（含主視覺 LoRA）     │
│  └─ 全部完成 → 上傳 R2                   │
│                                          │
│  Workflow C：動畫批次（依賴 B，async）    │
│  ├─ Loop 9 鏡頭                          │
│  ├─ fal.ai Kling 1.6 submit 到 queue    │
│  ├─ Postgres 記錄 request_id             │
│  ├─ Cron poll 每 30 秒                   │
│  └─ 全部完成 → 上傳 R2                   │
│                                          │
│  Workflow D：配音（依賴 A）                │
│  ├─ ElevenLabs API 跑旁白                │
│  └─ 上傳 R2                              │
│                                          │
│  Workflow E：通知（B+C+D 都完成後）        │
│  ├─ 整理 R2 → Google Drive 共享資料夾    │
│  └─ Email / LINE Notify 你               │
└─────────────────────────────────────────┘
             ↓
┌─────────────────────────────────────────┐
│  人工環節（剪接）                          │
│  CapCut 拉素材 → 剪接 → 字幕 → 輸出       │
└─────────────────────────────────────────┘
```

---

## 1️⃣ 基礎設施（Docker Compose）

### 推薦規格
- VPS：Hetzner CX22 (€4.5/月) 或 本機 Docker 都行
- RAM 建議 4GB+（Postgres + n8n + queue worker）
- Storage 20GB+（暫存生成中的素材）

### `docker-compose.yml`

```yaml
version: '3.8'

services:
  postgres:
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: n8n
      POSTGRES_USER: n8n
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./init-db.sql:/docker-entrypoint-initdb.d/init.sql
    healthcheck:
      test: ['CMD-SHELL', 'pg_isready -U n8n']
      interval: 5s
      timeout: 5s
      retries: 10

  n8n:
    image: n8nio/n8n:latest
    restart: unless-stopped
    ports:
      - "5678:5678"
    environment:
      - N8N_HOST=${N8N_HOST}
      - N8N_PORT=5678
      - N8N_PROTOCOL=https
      - WEBHOOK_URL=https://${N8N_HOST}/
      - DB_TYPE=postgresdb
      - DB_POSTGRESDB_HOST=postgres
      - DB_POSTGRESDB_PORT=5432
      - DB_POSTGRESDB_DATABASE=n8n
      - DB_POSTGRESDB_USER=n8n
      - DB_POSTGRESDB_PASSWORD=${POSTGRES_PASSWORD}
      - N8N_BASIC_AUTH_ACTIVE=true
      - N8N_BASIC_AUTH_USER=${N8N_USER}
      - N8N_BASIC_AUTH_PASSWORD=${N8N_PASSWORD}
      - EXECUTIONS_TIMEOUT=14400
      - EXECUTIONS_TIMEOUT_MAX=14400
      - N8N_PAYLOAD_SIZE_MAX=512
      - GENERIC_TIMEZONE=Asia/Taipei
      - N8N_DEFAULT_BINARY_DATA_MODE=filesystem
    volumes:
      - n8n_data:/home/node/.n8n
      - ./shared:/data/shared
    depends_on:
      postgres:
        condition: service_healthy

  caddy:
    image: caddy:2-alpine
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile
      - caddy_data:/data
      - caddy_config:/config
    depends_on:
      - n8n

volumes:
  postgres_data:
  n8n_data:
  caddy_data:
  caddy_config:
```

### `.env`

```
POSTGRES_PASSWORD=改成隨機長字串
N8N_USER=admin
N8N_PASSWORD=改成隨機長字串
N8N_HOST=n8n.yourdomain.com  # 自架要 DDNS 或買 domain
```

### `Caddyfile`（自動 HTTPS）

```
{$N8N_HOST} {
    reverse_proxy n8n:5678
}
```

### `init-db.sql`（建 queue 追蹤表）

```sql
CREATE TABLE IF NOT EXISTS animation_jobs (
    id SERIAL PRIMARY KEY,
    case_id VARCHAR(64) NOT NULL,
    shot_id VARCHAR(16) NOT NULL,
    request_id VARCHAR(128) NOT NULL UNIQUE,
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    image_url TEXT,
    video_url TEXT,
    prompt TEXT,
    submitted_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP,
    retry_count INT DEFAULT 0,
    error_message TEXT
);

CREATE INDEX idx_animation_jobs_status ON animation_jobs(status);
CREATE INDEX idx_animation_jobs_case_id ON animation_jobs(case_id);

CREATE TABLE IF NOT EXISTS cases (
    id VARCHAR(64) PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    builder_name VARCHAR(255),
    status VARCHAR(32) DEFAULT 'pending',
    script_v1_url TEXT,
    script_v2_url TEXT,
    review_report_url TEXT,
    drive_folder_url TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP
);
```

啟動：
```bash
docker compose up -d
docker compose logs -f n8n
```

---

## 2️⃣ 內部 Form（n8n Form Trigger）

不用 Google Form，n8n 內建有 Form Trigger node — 你登入 n8n 後台直接看到表單，submit 即觸發 workflow。

### Form 欄位設計

| 欄位 | 類型 | 範例 |
|---|---|---|
| `project_name` | Text | 松韻苑 |
| `builder_name` | Text | 陽光建設 |
| `location` | Text | 台中市北屯區 |
| `area_range` | Text | 28-42 坪 |
| `price_range` | Text | 1,500-2,400 萬 |
| `target_audience` | Dropdown | 首購族 28-38 / 換屋族 / 退休族 |
| `tone` | Dropdown | 溫馨家庭 / 質感品味 / 活力青春 / 高端尊榮 |
| `video_length` | Number | 60 |
| `platform` | Multi-select | FB / IG / YT / 接待中心 |
| `character_dna` | Long Text | 從 Cowork artifact 複製 |
| `character_image_url` | URL | 主視覺存放 R2 / Drive 的 URL |
| `character_nickname` | Text | 豆豆貓 |
| `story_outline` | Long Text | 自由描述 |
| `must_have` | Long Text | 必要元素（logo、捷運畫面等）|
| `taboos` | Long Text | 禁忌元素 |

n8n Form Trigger 會幫你產生一個內部 URL：
```
https://n8n.yourdomain.com/form-test/案件
```
點 Submit 後流入 Workflow A。

---

## 3️⃣ Workflow A：腳本生成（含 4 Reviewer 並行）

### 節點順序

```
[Form Trigger]
    ↓
[Set Node: 產生 case_id = nanoid()]
    ↓
[Postgres: INSERT cases]
    ↓
[HTTP Request: Anthropic API — 產 v1 腳本]
    ↓
[Code Node: 解析 JSON]
    ↓
[Split In Batches: 拆 4 個 reviewer 任務]
    ↓
[Sub-workflow x4 並行]：
    ├─ Reviewer 1（腳本）
    ├─ Reviewer 2（Prompt）
    ├─ Reviewer 3（法規）
    └─ Reviewer 4（品牌）
    ↓
[Merge: 收齊 4 份報告]
    ↓
[HTTP Request: Anthropic API — 產 v2 修正版]
    ↓
[Postgres: UPDATE cases SET script_v2_url, status='script_done']
    ↓
[Trigger Workflow B + Workflow D 並行]
```

### Anthropic API call（腳本生成）

```
POST https://api.anthropic.com/v1/messages
Headers:
  x-api-key: {{ $env.ANTHROPIC_API_KEY }}
  anthropic-version: 2023-06-01
  content-type: application/json

Body:
{
  "model": "claude-sonnet-4-6",
  "max_tokens": 8000,
  "system": "{{ $vars.script_system_prompt }}",
  "messages": [
    {
      "role": "user",
      "content": "請產出建案影片完整腳本。資料：{{ JSON.stringify($json) }}"
    }
  ]
}
```

### `script_system_prompt`（存在 n8n Variables）

```
你是專精於建案 AI 影片的腳本顧問。請依輸入資料產出 60 秒（或指定長度）影片完整腳本。

必須輸出 JSON 格式：
{
  "case_meta": { "project": "...", "length": 60, "platform": "..." },
  "concept": { "hook": "...", "story_arc": "...", "cta": "..." },
  "shots": [
    {
      "id": "S01",
      "duration_sec": 4,
      "scene": "...",
      "camera_movement": "...",
      "voiceover": "...",
      "subtitle": "...",
      "emotion": "...",
      "bing_prompt": "[豆豆DNA] + 場景具體描述",
      "kling_prompt": "[英文運鏡指令]"
    },
    ... (S01-S11)
  ],
  "voice_script_full": "全部旁白合在一起",
  "compliance_warnings": ["可能違規的點"],
  "bgm_keywords": ["heartwarming home", "gentle piano"]
}

重要規則：
- 每個 bing_prompt 開頭都要先放完整 character_dna
- 不要寫 9:16 / --ar 等參數，DALL-E 3 不認
- 不要寫中文字渲染（印章類用空白後製）
- 三層 Pixar 強制：「3D Pixar animated movie scene」「NOT photorealistic」「cartoon stylized」
- 人物描述用「Asian cartoon couple in Pixar 3D animation style」
- slogan 不可用「最」字（公平交易法）
- 距離不可寫具體分鐘數（用「鄰近」）
- 公設、機能描述加「示意」「以契約為準」
```

### 4 個 Reviewer Sub-workflow

每個 sub-workflow 接收 `script_v1` + `case_meta`，呼叫 Claude Haiku（便宜快速）：

```
POST https://api.anthropic.com/v1/messages
Body:
{
  "model": "claude-haiku-4-5-20251001",
  "max_tokens": 4000,
  "system": "{{ $vars['reviewer_' + reviewer_id + '_prompt'] }}",
  "messages": [
    {
      "role": "user",
      "content": "腳本：{{ JSON.stringify(script_v1) }}"
    }
  ]
}
```

每個 reviewer prompt 從你已經寫好的 `02_Reviewers.md` 拆出來，分別存進 n8n Variables：
- `reviewer_1_prompt`（腳本）
- `reviewer_2_prompt`（Prompt 技術）
- `reviewer_3_prompt`（法規）
- `reviewer_4_prompt`（品牌）

要求每個 reviewer 也輸出 JSON：
```json
{
  "score": 8,
  "red_flags": [...],
  "yellow_flags": [...],
  "suggestions": [...],
  "approved": false
}
```

### v2 修正

收齊 4 份 report 後，再呼叫 Claude Sonnet：

```
System: 你是建案影片腳本修正專家。根據 4 位 reviewer 報告修正 v1 腳本，產出 v2。
        所有 red_flags 必修，yellow_flags 至少修一半。
        保持 JSON 結構不變，只改值。

User: v1 腳本：{{ script_v1 }}
      4 份報告：{{ reviews }}
```

---

## 4️⃣ Workflow B：圖片批次（fal.ai Flux Pro）

### 節點

```
[Trigger from Workflow A]
    ↓
[Set: 取出 v2.shots[]]
    ↓
[Split In Batches: 9 個鏡頭]
    ↓
[HTTP Request: fal.ai Flux Pro queue]
    ↓
[Wait + Polling 直到 completed]
    ↓
[Download image binary]
    ↓
[Cloudflare R2 / S3 PUT]
    ↓
[Postgres UPDATE shot_images]
    ↓
[Loop 直到 9 張完成]
    ↓
[Trigger Workflow C]
```

### fal.ai Flux Pro（含 IP-Adapter 維持角色一致性）

```
POST https://queue.fal.run/fal-ai/flux-lora
Headers:
  Authorization: Key {{ $env.FAL_API_KEY }}
  Content-Type: application/json

Body:
{
  "prompt": "{{ $json.bing_prompt }}",
  "image_size": "portrait_16_9",
  "num_inference_steps": 28,
  "guidance_scale": 3.5,
  "loras": [
    {
      "path": "{{ $('Form Trigger').item.json.character_image_url }}",
      "scale": 0.8
    }
  ],
  "num_images": 4,
  "enable_safety_checker": true
}

Response:
{
  "request_id": "abc123...",
  "status_url": "https://queue.fal.run/.../status",
  "response_url": "https://queue.fal.run/.../"
}
```

### Polling 模式（loop 直到 status === "COMPLETED"）

n8n 用 `Wait` node + `IF` node + 自我循環 30 秒一次。

或者更穩：用 fal.ai 的 webhook 模式，submit 時帶 `webhook_url`：
```
{
  ...
  "webhook_url": "https://n8n.yourdomain.com/webhook/fal-image-done?case_id={{ case_id }}&shot_id={{ shot_id }}"
}
```

完成後 fal.ai 主動 callback 你的 webhook，效率高。

---

## 5️⃣ Workflow C：動畫批次（fal.ai Kling 1.6，async）

### 節點（這是最複雜的）

```
[Trigger from Workflow B]
    ↓
[Loop 9 shots]
    ↓
[HTTP Request: fal.ai Kling submit]
    ↓
[Postgres INSERT animation_jobs]
    ↓ （主 workflow 先結束，把工作交給 cron）

[獨立 Cron Workflow: 每 30 秒]
    ↓
[Postgres SELECT pending jobs]
    ↓
[Loop each]
    ↓
[HTTP Request: fal.ai status check]
    ↓
[IF completed]
    ├─ Download video
    ├─ Upload R2
    ├─ Postgres UPDATE status='done', video_url
    └─ Check 是否該 case 全部完成 → Trigger E

[IF still running] → 跳過等下一輪
[IF failed] → retry_count++ → 超過 3 次標 failed
```

### fal.ai Kling 1.6 submit

```
POST https://queue.fal.run/fal-ai/kling-video/v1.6/standard/image-to-video
Headers:
  Authorization: Key {{ $env.FAL_API_KEY }}

Body:
{
  "prompt": "{{ $json.kling_prompt }}",
  "image_url": "{{ $json.image_r2_url }}",
  "duration": "5",
  "aspect_ratio": "9:16"
}

Response:
{
  "request_id": "xyz789..."
}
```

### Status check

```
GET https://queue.fal.run/fal-ai/kling-video/v1.6/standard/image-to-video/requests/{{ request_id }}/status
```

### 結果取出

```
GET https://queue.fal.run/fal-ai/kling-video/v1.6/standard/image-to-video/requests/{{ request_id }}
```

回傳：
```json
{
  "video": {
    "url": "https://fal.media/files/.../output.mp4"
  }
}
```

⚠️ **重要**：fal.ai 的 video URL **只活 24 小時**，必須立刻下載到你自己的 R2。

---

## 6️⃣ Workflow D：配音（ElevenLabs，最簡單）

### 節點

```
[Trigger from Workflow A]
    ↓
[Set: 取出 voice_script_full]
    ↓
[HTTP Request: ElevenLabs TTS]
    ↓
[Save binary as audio.mp3]
    ↓
[Upload R2]
    ↓
[Postgres UPDATE voice_url]
```

### ElevenLabs API

```
POST https://api.elevenlabs.io/v1/text-to-speech/{{ voice_id }}
Headers:
  xi-api-key: {{ $env.ELEVENLABS_API_KEY }}
  Content-Type: application/json
  Accept: audio/mpeg

Body:
{
  "text": "{{ voice_script_full }}",
  "model_id": "eleven_multilingual_v2",
  "voice_settings": {
    "stability": 0.45,
    "similarity_boost": 0.80,
    "style": 0.15,
    "use_speaker_boost": true
  }
}

Response: binary mp3
```

### 預先選定中文聲音

去 https://elevenlabs.io/voice-library 試聽找到合適的 → 加進 My Voices → 取得 `voice_id` → 存進 n8n env。

推薦先試這幾個 ID：
- `XB0fDUnXU5powFXDhCwa` (Charlotte)
- `pFZP5JQG7iQjIQuC4Bku` (Lily)
- 或在 voice library 搜 "Mandarin warm female" 自選

---

## 7️⃣ Workflow E：通知 + 交付

```
[Trigger when B+C+D 都完成]
    ↓
[Code Node: 整理檔案結構]
    ↓
[Google Drive: 建專案資料夾]
    ↓
[Google Drive: 從 R2 同步檔案進去]
    ↓
[Google Drive: 設為「知道連結即可瀏覽」]
    ↓
[HTTP Request: Resend / SendGrid 寄信給你]
    ↓
[或 LINE Notify token call]
```

### 通知範例

```
主旨：[自動化] 松韻苑 — 素材包已備齊

案件 ID：松韻苑_2026-04-29_001
建商：陽光建設
影片長度：60 秒

📦 素材包：https://drive.google.com/drive/folders/...

包含：
  ✅ 腳本 v2 + Review 報告
  ✅ 9 張關鍵畫面（Bing/Flux 已完成）
  ✅ 9 段動畫（Kling 已完成）
  ✅ 旁白 mp3（ElevenLabs 已完成）
  ✅ .srt 字幕檔

🎬 下一步：CapCut 串接

⏱ 處理時間：18 分 32 秒
💰 API 成本：$3.85
```

---

## 8️⃣ 儲存策略（Cloudflare R2 推薦）

### 為什麼選 R2 而不是 S3
- 0 出口流量費（傳給 Drive 或自己下載都不收費）
- 每月 10GB 免費
- API 完全相容 S3
- 簽名 URL 機制完整

### 結構規劃

```
r2://building-videos/
  └── cases/
      └── {case_id}/
          ├── master_character.png      （原始主視覺）
          ├── script/
          │   ├── v1.json
          │   ├── v2.json
          │   └── reviews.json
          ├── images/
          │   ├── S01.png ... S09.png
          ├── videos/
          │   ├── S01.mp4 ... S09.mp4
          ├── audio/
          │   └── voiceover.mp3
          └── metadata.json
```

n8n S3 node 直接設 `endpoint: https://<account_id>.r2.cloudflarestorage.com` 即可。

---

## 9️⃣ 必踩的 5 個雷

### 雷 1：Claude API 的 JSON 輸出有時候會「破」

模型偶爾會多包一層 markdown：
```
這是您要的腳本：
```json
{...}
```
```

n8n Code node 要寫保險：

```javascript
const raw = $input.first().json.content[0].text;
// 嘗試直接 parse
let script;
try {
  script = JSON.parse(raw);
} catch (e) {
  // 抽出 ```json...``` 區塊
  const match = raw.match(/```json\s*([\s\S]*?)\s*```/);
  if (match) script = JSON.parse(match[1]);
  else throw new Error('Cannot parse Claude response');
}
return { json: script };
```

更穩做法：用 Claude API 的 **tool use** 強制 JSON schema 輸出。

### 雷 2：fal.ai Kling 偶爾 30 分鐘還沒結果

Cron 設 retry：5 分鐘沒完成 → 重新 submit。Postgres 紀錄 retry_count，3 次失敗就標 failed 寄 alert。

### 雷 3：ElevenLabs 中文偶爾跳出英文發音

中文字串中夾雜阿拉伯數字「11 樓」會被讀成「eleven 樓」。

解法：n8n Code node pre-process：
```javascript
text = text.replace(/(\d+)/g, (n) => '十一'); // 簡化版，實作要寫完整中文數字轉換
```

或在腳本生成階段就請 Claude 直接寫「十一樓」。

### 雷 4：fal.ai Webhook 來自不同 IP，要在 Caddy 放行

```
{$N8N_HOST} {
    @webhooks path /webhook/*
    handle @webhooks {
        reverse_proxy n8n:5678
    }
    handle {
        @internal {
            client_ip 你的固定IP
        }
        reverse_proxy @internal n8n:5678
        respond 403
    }
}
```

主後台只允許你 IP，但 webhook 路徑全網路開放（fal.ai 才能 callback）。

### 雷 5：Workflow B/C 同時跑會撞 fal.ai rate limit

Free tier 約 5 並行請求。`Split In Batches` 用 `Batch Size: 3` + `Wait Between: 2 seconds`。

---

## 🔟 建置順序建議

不要一口氣做完，**逐步驗證**：

### Week 1：腳本骨幹
- Day 1：Docker compose 跑起來、Postgres 連通
- Day 2：n8n Form Trigger → Claude API → 印出 JSON
- Day 3：4 Reviewer 並行 + Merge
- Day 4：v2 修正版
- Day 5：5 個假案測試 + 修 prompt

**驗證**：填表單 → 5 分鐘後 console 看到完整 v2 腳本 JSON

### Week 2：圖片自動化
- Day 6-7：fal.ai Flux Pro（無 LoRA 先簡單版）
- Day 8：R2 上傳
- Day 9：加 LoRA 維持角色一致性
- Day 10：3 個案子測試

**驗證**：填表單 → 10 分鐘後 R2 有 9 張圖

### Week 3：動畫 async
- Day 11-12：fal.ai Kling submit + Postgres
- Day 13-14：Cron polling
- Day 15：Webhook callback 模式（替代 polling）

**驗證**：填表單 → 20 分鐘後 R2 有 9 段 mp4

### Week 4：配音 + 通知
- Day 16：ElevenLabs API
- Day 17：Google Drive 整理
- Day 18：Email/LINE 通知
- Day 19-20：端到端測試 + 文檔

**驗證**：填表單 → 25 分鐘後信箱收到 Drive 連結

---

## 1️⃣1️⃣ 月費實際試算（每月 10 案）

```
基礎設施
├─ Hetzner CX22 VPS：$5
├─ Domain：$1（攤提）
├─ Cloudflare R2：$2（含流量）
└─ 小計：$8

API 變動成本（每案）
├─ Claude Sonnet（腳本生成）：$0.05
├─ Claude Haiku x4（reviewer）：$0.02
├─ Claude Sonnet（v2 修正）：$0.05
├─ fal.ai Flux Pro 25 張：$1.00
├─ fal.ai Kling 9 段：$2.70
├─ ElevenLabs 200 字：$0.04
└─ 單案小計：$3.86

10 案總計：$8 + $38.6 = $47/月

每案毛利（接案 NT$15,000）：99.2%
```

---

## 1️⃣2️⃣ 你下一步先做什麼

按你已經有的條件，建議走法：

1. **先把 Docker compose 跑起來**（1-2 小時）
2. **建 Form Trigger + Claude 腳本生成那段**（4-6 小時）
3. **塞自己的 5 個假案測試**腳本品質
4. **再決定要不要繼續做圖+動畫**

理由：腳本生成是**最高 ROI 的環節**（最耗腦力、最重複），先打通這段你立刻省每案 1-2 小時。圖+動畫的 ROI 比較低（節省的時間反而是「等待」）。

---

## 📞 需要再聊的部分

直接告訴我：

1. **「給我 Workflow A 完整 JSON 可以 import 到 n8n」** → 我直接寫 export 格式
2. **「Claude API system prompt 完整版」** → 我把腳本生成 + 4 reviewer + v2 修正的 prompt 全部寫好
3. **「fal.ai Flux LoRA 訓練流程細節」** → 從主視覺圖訓練 LoRA 的具體步驟
4. **「FFmpeg 自動剪接也順便給我」** → 雖然不在 MVP，但我可以給你模板，未來擴充用
5. **「整套架構畫成 Mermaid 圖」** → 給你方便看的架構圖

哪個先？

