# AI Video 操作手冊

## 你需要準備的帳號

| 帳號 | 用途 | 怎麼申請 | 費用 |
|------|------|----------|------|
| **fal.ai** | 圖片生成 | https://fal.ai → 註冊 → Dashboard → API Keys | 儲值制，建議先充 $10 |
| **Kling AI** | 影片生成 | https://klingai.com → 開發者中心 → API Keys | 買資源包，建議先買 $20 |
| **Azure** | 配音（台灣腔） | https://portal.azure.com → 建立 Speech Service（免費層） | 免費 50 萬字/月 |
| **AWS** | 影片剪接 | https://aws.amazon.com → 免費帳號 → IAM → 建立 Access Key | Lambda 免費層 |

## 安裝步驟

### 1. 下載專案

```bash
git clone https://github.com/y2024933/aivideo.git
cd aivideo
```

### 2. 設定環境變���

```bash
cp .env.example .env
cp .env.example .env.docker
```

編輯 `.env` 和 `.env.docker`，填入你的 API keys：

```bash
# 改成你的 keys
FAL_API_KEY=你的_fal_api_key
KLING_ACCESS_KEY=你的_kling_access_key
KLING_SECRET_KEY=你的_kling_secret_key
AZURE_TTS_KEY=你的_azure_tts_key
AWS_ACCESS_KEY_ID=你的_aws_key
AWS_SECRET_ACCESS_KEY=你的_aws_secret

# 開啟真實 API（不填就用假資料測試）
APP_USE_REAL_APIS=true
```

### 3. 啟動

```bash
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec vite npm install
```

### 4. 開啟瀏覽器

http://localhost:8010

```
帳號：admin@aivideo.local
密碼：password
```

---

## 操作流程（每支影片）

### Step 1：在 Claude Cowork 產腳本

1. 打開 Claude（claude.ai）或 Gemini
2. 貼入建案資料，請 AI 產出：
   - 角色 DNA（英文 prompt）
   - 9 個鏡頭的場景描述、旁白、字幕
   - 每個鏡頭的 Flux prompt 和 Kling prompt
3. 請 AI 跑 4 位 Reviewer 審查（參考 `09_腳本參考/02_Reviewers.md`）
4. 修正後拿到 v2 腳本

**預計時間：30-60 分鐘**

### Step 2：在系統建立建案

1. 點「新建案」
2. 填入建案基本資料（名稱、建商、地點、客群、調性）
3. 貼入角色 DNA
4. 逐一填入 9 個 shot 的資料（從 Cowork 複製）：
   - 場景描述
   - 旁白
   - 字幕
   - Flux prompt（圖片用）
   - Kling prompt（動畫用）
   - 秒數
5. 按「建立建案並開始」

### Step 3：核准角色（Checkpoint 1）

1. 系統自動產 4 張角色預覽圖（約 30 秒）
2. 你看 4 張圖，選最好的一張
3. 按「選這個」核准

**不滿意？** 按「重新生成角色」再跑一次

### Step 4：確認腳本

1. 檢視分鏡內容是否正確
2. 按「確認腳本，生成場景圖」

### Step 5：核准場景圖（Checkpoint 2）

1. 系統自動產 9 張場景圖（約 1-2 分鐘）
2. 用核准的角色圖做參考，確保角色一致
3. 檢視 3x3 grid：
   - 綠燈 = 完成
   - 紅燈 = 失敗（可重跑）
   - 黃燈 = 生成中
4. 全部滿意後按「全部核准，開始生成動畫」

**某張不滿意？** 之後會加單張重跑功能

### Step 6：等待動畫 + 配音（自動）

1. 系統自動：
   - 把 9 張圖送到 Kling API 轉成 5 秒動畫
   - 每段動畫約 1-3 分鐘
2. 動畫全部完成後，按「生成配音」
3. 系統用 Azure TTS 產出台灣腔配音（約 5 秒）

**動畫失敗？** 頁面會顯示哪幾段失敗，等待系統之後加重跑功能

### Step 7：渲染成品（Checkpoint 3）

1. 確認所有動畫 ✅ + 配音 ✅
2. 按「渲染最終影片」
3. 系統自動：
   - 把 9 段動畫 + 配音 + 字幕 + 浮水印 + BGM 組裝
   - Remotion Lambda 在 AWS 上渲染
   - 約 1-2 分鐘
4. 渲染完成後，頁面顯示影片預覽
5. 按「下載影片」

**預計時間：等待 5-10 分鐘（全自動）**

### Step 8：精修（可選）

如果 75-85 分的成品不夠好，下載素材去 CapCut 精修：
- 加更精細的轉場
- 調整 BGM 卡拍點
- 加文字動畫特效

---

## 整體時間

| 步驟 | 時間 | 誰做 |
|------|------|------|
| Cowork 產腳本 | 30-60 分 | 你 |
| 系統填表 | 10-15 分 | 你 |
| 角色預覽 | 30 秒 | 系統 |
| 核准角色 | 1 分 | 你 |
| 場景圖生成 | 1-2 分 | 系統 |
| 核准場景圖 | 2 分 | 你 |
| 動畫生成 | 10-20 分 | 系統 |
| 配音 | 5 秒 | 系統 |
| 渲染成品 | 1-2 分 | 系統 |
| **總計** | **~45-90 分** | |

## 費用

每支 60 秒影片約 **$4.8 USD**，無月費。

| 項目 | 費用 |
|------|------|
| 角色預覽 4 張 | $0.16 |
| 場景圖 9 張 | $0.36 |
| 動畫 9 段 x 5s | $1.89 |
| 動畫重跑（預估 5 段） | $1.05 |
| 配音 | $0（免費額度） |
| 剪接 | $0.05 |
| **合計** | **~$3.5-5.0** |

---

## 常見問題

### 角色圖長得不一樣怎麼辦？
Flux Kontext 用核准的角色圖做參考，一致性約 85-90%。如果某張差太多，重跑該鏡頭。

### 動畫一直在生成中？
Kling 通常 1-3 分鐘完成。如果超過 5 分鐘，系統會自動標記失敗。重新跑 approve-images 即可。

### 配音聽起來怪怪的？
Azure TTS 的 `zh-TW-HsiaoChenNeural` 是預設女聲。可以換成 `zh-TW-YunJheNeural`（男聲）或 `zh-TW-HsiaoYuNeural`（另一個女聲）。

### 數字被唸成英文？
系統會自動轉換：「11 樓」→「十一樓」。如果還有漏掉的，在配音稿裡直接寫中文數字。

### 影片畫質不夠好？
- 圖片：Flux Kontext 預設 1024px，足夠 1080p
- 動畫：Kling std 模式 = 720p。要 1080p 改用 pro 模式（$0.35/段）
- 渲染：Remotion 預設 1080x1920

### 怎麼看花了多少錢？
每個建案頁面右上角顯示累計費用。
