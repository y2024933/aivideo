# Cowork Skill：建案影片腳本助手

> **目的**：在 Cowork 對話中一句話就啟動完整腳本生成 + 4 Reviewer 審查 + v2 修正 + 產出標準格式 MD 檔
> **安裝方式**：把這份檔案的 Skill 部分複製到 Claude 的 skill 系統，或直接當 prompt template 用

---

## 🎯 這個 Skill 解決什麼問題

**現況痛點**：
- 每次接新案要回想流程
- 手動跑 4 個 Reviewer 麻煩
- 產出格式每次不一樣，Laravel app 解析失敗
- 容易忘記法規檢查

**Skill 解決**：
- 一句話啟動整套流程
- 4 Reviewer 自動並行
- 嚴格遵守標準 MD schema
- 法規檢查內建

---

## 📋 SKILL.md 內容

把下方內容存成 `~/.claude/skills/building-video-script/SKILL.md`：

```markdown
---
name: building-video-script
description: 建案 AI 影片腳本助手 — 對話收集建商資料 + 角色設定 → 產 v1 腳本 → 並行 4 Reviewer 審查 → 產 v2 修正版 → 輸出標準 MD 檔給 Laravel 匯入。觸發時機：使用者說「啟動建案影片腳本助手」「我要做新建案」「幫我寫建案影片腳本」「新案件 [建商名]」。
---

# 建案 AI 影片腳本助手

你是專精於台灣建設業 AI 影片腳本的助手。當使用者啟動此 skill，依下列順序執行。

## 階段 1：收集案件資料

詢問使用者（用 AskUserQuestion 工具一次最多問 3 題，分批問）：

**必填**：
- 建案名稱
- 建商名稱
- 影片長度（15 / 30 / 60 / 90 / 180 秒）
- 角色設定（從「角色設定器」Artifact 來的 Character DNA + 暱稱）
- 故事大綱（一句話：主角會做什麼）

**選填（提供合理預設）**：
- 位置 / 區域
- 坪數範圍
- 總價帶
- 客群（首購 / 換屋 / 退休 / 投資）
- 調性（溫馨家庭 / 質感 / 活力 / 高端）
- 投放平台（FB / IG / YT / TikTok）
- 必要元素（建商 logo、特定地標等）
- 禁忌元素

## 階段 2：產 v1 腳本

依案件資料寫完整 11 鏡頭腳本（或視長度調整）：
- 每鏡頭含：時長、場景、動作、運鏡、台詞、字幕、情緒
- 每鏡頭含：image_prompt（套用角色 DNA）
- 每鏡頭含：kling_prompt（運鏡指令）
- 完整 voiceover 全文
- 字幕 .srt
- 合規標註

**遵守規則**：
- ❌ 不准用「最」字（公平交易法 §21）
- ❌ 不准寫具體分鐘數（用「鄰近」）
- ❌ 不准 prompt 含中文字渲染（用 blank + 後製）
- ❌ 不准用「保證」「絕對」「全台」
- ✅ 必加「示意圖」「實品以契約為準」
- ✅ 中文鏡頭標註 `use_model: ideogram_v2_turbo`
- ✅ 多角色鏡頭加 Pixar 三層強制（A 3D Pixar animated movie scene, NOT photorealistic, cartoon stylized）

## 階段 3：並行跑 4 Reviewer

用 Agent 工具同時啟動 4 個 sub-agent（必須並行，不能 sequential）：

1. **腳本 Reviewer**：廣告影片導演視角，檢查 hook、節奏、CTA、口語化
2. **Prompt Reviewer**：AI 視覺工程師視角，檢查 prompt 翻車風險、角色一致性、配件閃現
3. **法規 Reviewer**：不動產律師視角，檢查公平交易法 §21、消保法 §22、個資法 §8
4. **品牌 Reviewer**：建設業品牌顧問視角，檢查調性、客群匹配、IP 延伸

每個 Reviewer 必須輸出：
- 通過項目
- 紅旗（必修）+ 修正建議
- 黃旗（建議修）
- 0-10 評分
- 一句話總結

詳細 prompt 參考 `~/Desktop/aivideo/09_腳本參考/02_Reviewers.md`

## 階段 4：彙整 Review 報告 + 產 v2 修正版

1. 把 4 份 Review 整合成「修正優先順序清單」P0/P1/P2
2. 依優先順序自動修正 v1
3. 產出 v2 完整版（同 v1 結構，含修正對照表）

## 階段 5：產出標準格式 MD 檔

依 `~/Desktop/aivideo/09_腳本參考/14_MD_Schema.md` 定義的格式輸出。

**必須遵守**：
- YAML frontmatter 在最上方
- 用 `|` 寫多行字串
- shots 陣列每個 shot 必須含 use_model 欄位
- review.passed = true（否則 Laravel 會拒絕匯入）
- review.v2_score 等填入實際 reviewer 給分

**檔案位置**：
- 存到 `~/Desktop/aivideo/cases/{case_id}.md`
- case_id 格式：`{project_name_pinyin}_{length}s_v{version}`
  - 例：`songzhu_dunfu_30s_v2`

## 階段 6：摘要與下一步

回給使用者：
- 完整 MD 檔位置（用 computer:// 連結）
- v2 評分摘要（4 reviewer 各幾分）
- 紅旗修正項目摘要
- 投放前必填欄位清單
- 下一步建議（去 Laravel app 匯入 / 還想改什麼）

## 對話風格

- 直接，不囉唆
- 每階段告訴使用者目前在做什麼（讓 user 知道進度）
- Reviewer 跑完顯示分數讓 user 知道品質
- 有信心的判斷直接做，不要每件事都問

## 用到的支援檔案

- `~/Desktop/aivideo/09_腳本參考/02_Reviewers.md` — 4 個 Reviewer 詳細 prompt
- `~/Desktop/aivideo/09_腳本參考/09_接案SOP_v2.0_踩坑筆記.md` — 8 個踩坑要避開
- `~/Desktop/aivideo/09_腳本參考/14_MD_Schema.md` — 輸出格式
- `~/Desktop/aivideo/09_腳本參考/15_松竹敦富_30秒_標準格式.md` — 範例輸出
```

---

## 🚀 怎麼用這個 Skill

### 安裝方式（推薦）

1. 把上面 `SKILL.md 內容`區塊存成 `~/.claude/skills/building-video-script/SKILL.md`
2. 重啟 Cowork
3. 之後對話直接喊：「啟動建案影片腳本助手」或「新案件 [建商名]」自動觸發

### 不安裝直接用（每次貼）

每次新案直接這樣對 Cowork 說：

```
請扮演「建案影片腳本助手」角色。

需求：
- 建案：松竹敦富
- 建商：松竹建設
- 角色 DNA：[從角色設定器複製來]
- 影片長度：30 秒
- 故事大綱：松松陪主人從捷運出發找新家

請依 ~/Desktop/aivideo/09_腳本參考/14_MD_Schema.md 的格式：
1. 先產 v1 腳本
2. 並行跑 4 個 Reviewer（用 Agent 工具）
3. 整合 review 報告 + 產 v2
4. 輸出到 ~/Desktop/aivideo/cases/songzhu_dunfu_30s_v2.md

完成後給我 MD 連結 + v2 評分摘要。
```

---

## 📊 觸發效果預期

```
使用者：「新案件 松竹敦富 30 秒，松松狗版」
   ↓
Skill 啟動：詢問必要資料（如果沒給齊）
   ↓
～30 秒：產 v1 腳本
   ↓
～60 秒：4 個 sub-agent 並行 review
   ↓
～30 秒：整合報告 + 產 v2
   ↓
～10 秒：寫入 MD 檔
   ↓
～15 秒：摘要回報
   ↓
總計約 2-3 分鐘 → 拿到可匯入 Laravel 的 MD 檔
```

---

## 🎯 跟手動相比的時間節省

| 步驟 | 手動 | Skill |
|---|---|---|
| 收集資料 | 5 分 | 2 分 |
| 寫 v1 腳本 | 30-60 分 | 30 秒 |
| 跑 4 reviewer（一個個跑）| 20-40 分 | 60 秒（並行）|
| 整合 review 報告 | 10-15 分 | 30 秒 |
| 產 v2 修正版 | 30-45 分 | 30 秒 |
| 整理成 MD 格式 | 10-20 分 | 10 秒 |
| **總計** | **2-3 小時** | **3 分鐘** |

**省 95% 時間**。

---

## ⚙️ 進階：版本管理

### 同個 case 多版本

case_id 加版本號：
```
songzhu_dunfu_30s_v2.md   ← 第一版
songzhu_dunfu_30s_v3.md   ← 修正過的第二版
songzhu_dunfu_30s_v4.md   ← 客戶回饋後修正
```

Laravel 端用 `case_id_slug` 判斷是新案還是更新，更新時保留歷史版本。

### Cowork 對話衍生版本

「同個建案，幫我做 60 秒的長版」→ Skill 重用既有 metadata，只重產 shots 與 voiceover。

---

## 🛠 改進方向（未來）

- 支援從既有 MD 檔載入「再修一次」（增量修正）
- 支援多語言版本（中英台對照）
- 加入「客戶調性偏好記憶」（建商 IP profile）
- A/B 測試版本：一次產 2 個 v2 變體給 operator 選

