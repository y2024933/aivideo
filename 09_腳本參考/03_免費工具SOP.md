# 免費工具操作 SOP

> **目的**：用 0 元工具完成一支建案影片，每個工具都有額度限制和小技巧，這份檔案告訴你怎麼把它們組合起來最有效率。

---

## 工具總覽（建議的免費組合）

| 環節 | 工具 | 免費額度 | 替代方案 |
|---|---|---|---|
| 產圖 | **Bing Image Creator** | 每天 15 次「快速」+ 無限慢速 | Leonardo.ai (每天 150 點) |
| 產動畫 | **Kling AI** | 每天 6 段（5 秒） | Luma Dream Machine |
| 產動畫補強 | **Luma Dream Machine** | 每月 30 段 | Hailuo (每天 3 次) |
| 配音 | **ElevenLabs** | 每月 10,000 字 | Microsoft Edge TTS |
| 字幕 / 剪接 | **CapCut 免費版** | 完全免費 | 剪映專業版 |

---

## 1️⃣ Bing Image Creator（產圖主力）

### 🌐 網址
https://www.bing.com/create
（需要 Microsoft 帳號，沒有的話花 1 分鐘註冊）

### 📋 操作步驟

1. 登入後在輸入框貼 prompt
2. 按「Create」
3. 等 10-30 秒（如果有 Boost 點數會比較快）
4. 一次產出 4 張變化圖
5. 右鍵 → 儲存圖片

### 🎯 維持角色一致的關鍵技巧

**重點**：DALL-E 3（Bing 用的引擎）**沒有 `--cref` 之類的角色參考參數**，所以要靠「**重複使用同一段角色描述**」才能讓角色每張都長得一樣。

✅ 正確做法：
```
[ 角色 DNA - 完全照貼，一字不改 ]，[ 鏡頭 1：在捷運上看地圖 ]
[ 角色 DNA - 完全照貼，一字不改 ]，[ 鏡頭 2：在客廳沙發上 ]
[ 角色 DNA - 完全照貼，一字不改 ]，[ 鏡頭 3：在陽台看夜景 ]
```

❌ 錯誤做法：
```
鏡頭 1：橘貓在捷運上
鏡頭 2：胖橘貓在客廳
鏡頭 3：可愛的橘貓在陽台
```
（每次描述都不同 → 每張長得都不同）

### 🚨 避開內容過濾

Bing 的內容過濾比 Midjourney 嚴格很多，以下詞彙會觸發：
- 任何性暗示詞（即使無辜）
- 「woman/girl in bedroom」這類組合
- 知名人物（即使你只想要他的風格）
- 暴力、武器、藥物相關

**安全替代詞**：
| ❌ 觸發詞 | ✅ 替代寫法 |
|---|---|
| sexy / hot | elegant / charming |
| young woman in bedroom | young woman in bright living room |
| blood / red liquid | dark red wine |

### 💡 Bing 進階小技巧

- **Boost 點數**：每天 15 點，用快了會變慢速（10-30 秒變 1-3 分鐘），但仍可繼續用
- **時段策略**：台灣時間早上 9 點前 / 晚上 12 點後比較順（亞洲尖峰前後）
- **批次產圖**：同一個 prompt 多按幾次「Create」，可以拿到不同變化（每次 4 張，按 5 次就 20 張）

### ⏱️ 60 秒影片需要多少張？

```
60 秒 ÷ 4 秒/鏡頭 ≈ 15 個鏡頭
每個鏡頭挑 1-2 張最好的 → 需要產 30-40 張
按 Bing 每次 4 張計算 → 跑 8-10 次 → 約 30-60 分鐘
（看 Bing 當下速度而定）
```

---

## 2️⃣ Leonardo.ai（產圖備援 / 風格穩定版）

### 🌐 網址
https://leonardo.ai

### 為什麼要備援
- Bing 拒絕生成某張圖時可以救你
- Leonardo 的風格控制比 Bing 更精準
- 有「Image Guidance」功能（上傳參考圖鎖定風格）

### 📋 操作步驟

1. 註冊免費帳號
2. 進 AI Image Generation
3. 風格選 **3D Animation** 或 **Anime General**
4. Model 選 **Leonardo Phoenix**（最新、品質好）
5. 貼 prompt，畫面比例選 16:9 或 9:16
6. 按 Generate（每張花 8-12 點，每天 150 點 ≈ 12-18 張）

### 💡 角色一致性招數

Leonardo 有「**Image Guidance**」功能（免費版每次扣 6 點）：
1. 把第一張產出的角色設定圖上傳
2. Strength 拉到 0.6-0.8
3. 後續鏡頭就會延續這個角色長相

效果比 Bing 好，但點數消耗大，**建議只用在重要鏡頭**。

---

## 3️⃣ Kling AI（產動畫主力）

### 🌐 網址
https://kling.kuaishou.com（國際版）
https://klingai.com（國際版備用）

### 📋 操作步驟

1. 註冊帳號（用 Google / Email 都可以）
2. 進 Image to Video 模式
3. 上傳剛剛 Bing 產出的圖
4. **Prompt 寫運鏡和動作**，例如：
   - `橘貓緩緩轉頭看向窗外，鏡頭從中景慢慢推近到特寫`
   - `cat slowly turns head looking out window, camera slowly pushes in from medium shot to close-up`
5. 時長選 5 秒（免費版上限）
6. 按 Generate，等 1-3 分鐘

### 🎬 運鏡指令對照表

| 中文 | 英文 prompt |
|---|---|
| 推軌（慢慢推近） | slow dolly in |
| 拉軌（慢慢拉遠） | slow dolly out |
| 環繞 | orbit around subject |
| 俯拍下降 | top-down shot, camera descends |
| 仰拍 | low angle, camera looking up |
| 跟拍 | tracking shot following subject |
| 推軌＋環繞 | dolly in while orbiting |
| 鏡頭穩定 | static camera, locked off shot |

### ⚠️ Kling 免費版的限制

- **每天 6 次免費生成**（5 秒一段）
- 高峰期（晚上）排隊很久（可能要等 5-10 分鐘）
- 解析度較低（720p）

### 📊 60 秒影片如何分配 Kling 額度

```
60 秒影片 ≈ 12-15 段 5 秒動畫
Kling 每天 6 段 → 需要 2-3 天才能跑完免費版

策略 A：分天跑（最省錢，最慢）
策略 B：用 Luma 補強（每月 30 段，補不夠的部分）
策略 C：部分鏡頭用「Ken Burns 效果」（CapCut 內建，靜圖加緩慢推軌也有動感）
```

### 💡 省 Kling 額度的小技巧

- **不是每個鏡頭都需要動畫**：靜止圖 + CapCut 的 Ken Burns 推軌效果，看起來也有動感
- **建案外觀、空景**：用靜圖 + CapCut 推軌（最省）
- **角色動作鏡頭**：必用 Kling（最值得花額度）
- **轉場鏡頭**：用 CapCut 內建轉場（免費）

---

## 4️⃣ Luma Dream Machine（產動畫補強）

### 🌐 網址
https://lumalabs.ai/dream-machine

### 為什麼選它補強 Kling
- 每月 30 段免費（Kling 每天 6 段，月底會不夠）
- 運鏡指令支援度更好（「camera dolly」「camera pan」這類指令很準）
- 可以「圖片接圖片」做轉場（兩張圖中間自動補幀）

### 📋 操作步驟

1. 用 Google 帳號登入
2. 上傳起始圖
3. 寫 prompt（建議用英文，運鏡指令更精準）
4. 可以加「End frame」（結束圖）→ Luma 會自動補中間
5. 等 2-5 分鐘渲染

### 💡 殺手鐧：圖片接圖片做轉場

Luma 獨特的「Keyframe」功能：
1. 第一張圖：建案外觀
2. 第二張圖：客廳內部
3. Luma 自動補幀 → 變成「鏡頭穿過外牆進到客廳」的酷炫轉場

這個 Kling 做不到，是 Luma 的最大優勢。

---

## 5️⃣ ElevenLabs（中文配音主力）

### 🌐 網址
https://elevenlabs.io

### 📋 操作步驟

1. 註冊免費帳號（每月 10,000 字額度）
2. 進 Text to Speech
3. **Voice 選**：
   - 中文女聲推薦：搜尋 `Rachel` 或 `Charlotte`，然後在 Settings 把語言設成 Chinese (Mandarin)
   - 中文男聲推薦：搜尋 `Adam` 或 `Antoni`
4. 貼配音稿
5. **Settings 調整**：
   - Stability：30-50（太高機械感、太低不穩定）
   - Similarity Boost：75-85
   - Style：0-30（建設業沉穩，不要太誇張）
6. 按 Generate
7. 滿意後 Download MP3

### 💡 進階技巧：情緒標記

ElevenLabs 支援在文字中插入情緒提示：

```
（語氣溫柔）
回家，是一天最美的儀式。

（語氣堅定，速度稍快）
松韻苑，給家最好的選擇。

（語氣柔軟，加重「家」字）
這裡，就是我們的家。
```

雖然括號內的文字不會被讀出來，但會影響語氣表達。

### ⏱️ 10,000 字夠用嗎？

```
60 秒影片旁白約 150-200 字
10,000 字 ÷ 200 字 = 約 50 支影片的旁白量

夠你跑很多支了（甚至包含試錯重產）
```

### ⚠️ 免費版限制

- 不能用於商業用途（嚴格來說）
- 不能下載 Studio Quality 級別
- **解法**：第一個練習案用免費版，正式接案就升級 Starter $5/月（解鎖商用）

---

## 6️⃣ Microsoft Edge TTS（完全免費備援配音）

### 為什麼要這個
- ElevenLabs 額度用完時的備援
- 完全免費、無限制
- 中文發音其實意外不錯（特別是 `xiaoxiao` 和 `yunjian` 兩個音）

### 📋 操作步驟（最簡單方法）

#### 方法 A：用 Edge 瀏覽器內建朗讀
1. Edge 瀏覽器打開任何網頁
2. 把配音稿貼到一個 .txt 檔案、用 Edge 開啟
3. 右鍵「大聲朗讀」
4. 用 OBS 或 QuickTime 錄畫面音訊

#### 方法 B：用線上工具（推薦）
1. 進 https://www.text-to-speech.io/
2. 選「Microsoft」聲音
3. 中文選 `zh-CN-XiaoxiaoNeural`（女聲，很自然）
4. 貼文字 → Generate → Download MP3

### 推薦中文聲音
| 聲音名稱 | 性別 | 風格 |
|---|---|---|
| zh-TW-HsiaoChenNeural | 女 | 台灣口音、自然 |
| zh-TW-YunJheNeural | 男 | 台灣口音、沉穩 |
| zh-CN-XiaoxiaoNeural | 女 | 中國口音、活潑 |
| zh-CN-YunjianNeural | 男 | 中國口音、廣告感 |

---

## 7️⃣ CapCut（剪接 + 字幕 + BGM 全包）

### 🌐 網址
https://www.capcut.com（網頁版）+ 桌面 App

### 為什麼是首選
- **完全免費**且功能完整
- 內建免費 BGM 庫（商用授權清楚）
- 自動上字幕（中文準確率 95%+）
- 內建 Ken Burns 推軌效果（救命神器）
- 一鍵輸出 16:9 / 9:16 / 1:1

### 📋 完整剪接流程

#### Step 1：素材整理
建一個資料夾結構：
```
專案名稱/
├── 01_image/    （Bing 產出的圖）
├── 02_video/    （Kling/Luma 產出的影片）
├── 03_audio/    （ElevenLabs 配音）
└── 04_export/   （最後輸出的 mp4）
```

#### Step 2：建立專案
- 打開 CapCut
- 新增專案 → 選比例（16:9 橫版 / 9:16 直版）

#### Step 3：拉素材上時間軸
- 把 02_video 裡的影片依分鏡順序拖上時間軸
- 沒有動畫的鏡頭：拉圖片，CapCut 預設會給 5 秒，**右鍵選「Ken Burns」**自動加推軌

#### Step 4：加配音
- 把 ElevenLabs 的 mp3 拉到音軌
- 對齊每個鏡頭

#### Step 5：自動上字幕
- 點配音音軌 → 右鍵 → **Auto Captions**
- 選「中文」→ 等 30 秒 → 自動出現字幕
- 字幕位置統一在下方 1/4 處（手機觀看舒適區）
- 字體建議：思源黑體 Bold / Noto Sans TC Bold

#### Step 6：加 BGM
- 進 CapCut 音樂庫
- 搜尋關鍵字：
  - 溫馨家庭：`heartwarming`、`family`、`gentle piano`
  - 時尚質感：`elegant`、`luxury`、`stylish`
  - 活力青春：`upbeat`、`fresh`、`positive`
- 確認標籤是「Free for commercial」（免費商用）
- BGM 音量壓到 -18dB 以下，不蓋過旁白

#### Step 7：加示意圖標註（建設業必須！）
- 在每個 AI 生成的鏡頭右下角加一個小字幕：「示意圖」
- 字體小但清楚可讀
- 整支影片片尾加註：「3D 示意圖，實際以建造完成為準」

#### Step 8：輸出
- 解析度：1080p（夠用）
- 幀率：30fps
- 格式：MP4 H.264
- 命名格式：`建案名稱_長度_版本日期.mp4`（例如 `松韻苑_60s_v1_20260429.mp4`）

---

## 完整時間預估（60 秒影片，純免費版）

| 環節 | 時間 |
|---|---|
| 1. 角色設定（角色設定器 + Bing 試圖）| 30-60 分 |
| 2. 腳本生成（Cowork + 範本）| 15-30 分 |
| 3. 4 位 Reviewer 審查 + 修正 | 30 分 |
| 4. 產所有鏡頭關鍵畫面（Bing）| 1-2 小時 |
| 5. 產動畫（Kling 6 段 + Luma 補 6 段）| 1.5-2 小時（含等待）|
| 6. 配音（ElevenLabs）| 15-30 分 |
| 7. CapCut 剪接 + 字幕 + BGM | 1.5-2 小時 |
| 8. 加示意圖標註 + 輸出 | 30 分 |
| **總計** | **5-8 小時** |

可以一個工作日做完一支 60 秒影片。

---

## 卡關處理

| 卡點 | 解法 |
|---|---|
| Bing 一直拒絕生成 | 檢查 prompt 有沒有觸發詞 → 改寫 → 還不行就用 Leonardo |
| Kling 排隊太久 | 換離峰時段 + 用 Luma 補 |
| 角色每張長不一樣 | 角色 DNA 是不是每次都完全照貼？檢查有沒有打錯字 |
| ElevenLabs 中文發音怪 | 在文字中加標點符號（逗號、句號要清楚） |
| CapCut 自動字幕不準 | 手動修，或用 ElevenLabs 配音前先確認文稿正確 |

