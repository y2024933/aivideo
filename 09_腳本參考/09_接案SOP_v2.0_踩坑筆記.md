# 建案 AI 影片接案 SOP v2.0
## 包含 2026-04-29 實測踩坑筆記

> **這份文件的核心目的**：把每次踩過的坑變成下次的捷徑，下次接案直接照這份操作，不用重複試錯。

> **適用工具組合**：免費版（Bing Image Creator + Kling + Luma + ElevenLabs + CapCut）

---

## 📚 目錄

1. [⚠️ 已知踩過的坑（必看）](#1-已知踩過的坑必看)
2. [🔥 破解公式速查表](#2-破解公式速查表)
3. [📋 標準 Prompt 模板（複製即用）](#3-標準-prompt-模板複製即用)
4. [🚀 標準工作流程（從接案到交付）](#4-標準工作流程從接案到交付)
5. [💎 升級判斷準則（什麼時候該付費）](#5-升級判斷準則什麼時候該付費)
6. [🛡️ 法規合規清單（不能省略）](#6-法規合規清單不能省略)

---

## 1. ⚠️ 已知踩過的坑（必看）

### 🚨 坑 #1：Bing 比例不是「9:16」是「4:7（垂直）」

**踩坑情境**：
v1 腳本所有 prompt 寫 `vertical 9:16 aspect ratio`，跑出來都是方形 1024×1024。

**真相**：
- DALL-E 3 **不認** prompt 裡的比例描述（`9:16`、`16:9`、`--ar` 全都不認）
- Bing UI 介面只有三個選項：**1:1（方形）/ 4:7（垂直）/ 7:4（水平）**
- `4:7` 比例（≈ 0.5714）跟 `9:16` 比例（≈ 0.5625）幾乎一樣，**完全可用於 IG/FB 直式投放**

**✅ 正確做法**：
1. 進 Bing 介面後**先點長寬比下拉選單，選「4:7（垂直）」**
2. Prompt 裡寫 `tall vertical portrait composition, full body framed top to bottom`（描述構圖，不寫比例）
3. 想做橫式 → 選「7:4（水平）」+ prompt 寫 `wide landscape composition`

---

### 🚨 坑 #2：DALL-E 3 不認任何 Midjourney 參數

**踩坑情境**：
從教學文看到 `--ar 9:16 --cref [圖片URL] --cw 100`，丟到 Bing 全部失效。

**真相**：
- `--cref`（角色參考）→ Midjourney 專屬，DALL-E 3 不支援
- `--ar`（比例）→ 同上，要靠 UI
- `--style`、`--v`、`--seed` → 通通不支援

**✅ 正確做法**：
- 維持角色一致性 → **重複使用同一段角色描述（DNA 文字）**，不要靠參數
- 比例 → UI 切換
- 風格 → 用自然語言描述（「3D Pixar style」「watercolor」這種）

---

### 🚨 坑 #3：DALL-E 3 渲染中文字必翻車

**踩坑情境**：
S09 印章 prompt 寫 `text reads "豆豆認證"`，跑出來變成「Chracter」「Sde Pelle」「Thegle」這種亂碼。

**真相**：
- DALL-E 3 對英文短字（5 字以內）成功率約 50%
- 對中文字 → **幾乎 100% 翻車**（變成相似形狀的亂碼）
- 即使對英文，多字組合也常拼錯

**✅ 正確做法**：
**永遠不要叫 DALL-E 3 畫文字**，改成：
1. Prompt 描述「空白印章」「空白看板」「空白標籤」
2. CapCut 後製階段加文字層
3. 字體、顏色、特效都更可控

**萬用替換句**：
```
原本：text reads "XXX"
改成：blank surface, text will be added in post-production
```

---

### 🚨 坑 #4：DALL-E 3 看到「真實人物」會切換寫實風

**踩坑情境**：
S07 prompt 包含「年輕亞洲夫妻」，DALL-E 3 自動把整張圖渲染成寫實照片風，把「3D Pixar style」忽略掉。

**真相**：
- DALL-E 3 對人物的預設模式是「寫實」
- 一旦 prompt 提到具體人物，動畫風格描述會被覆寫
- 這個 Bug 在「動物 + 人物同框」最嚴重

**✅ 破解公式（已驗證有效）**：
**三層 Pixar 強制**，三句話都要寫：
```
1. A 3D Pixar animated movie scene, fully cartoon stylized animation
2. NOT photorealistic
3. [人物描述前]：a young Asian cartoon couple rendered in Pixar 3D animation style
```

範例 prompt 開頭：
```
A 3D Pixar animated movie scene, fully cartoon stylized animation, NOT
photorealistic. A young Asian cartoon couple in their early 30s
rendered in Pixar 3D animation style, sitting on a beige sofa...
```

**驗證結果**：套用三層強制後，DALL-E 3 成功產出 Pixar 風的人物 + 貓家庭場景。

---

### 🚨 坑 #5：DALL-E 3 會「丟掉」次要元素

**踩坑情境**：
S07 第一次嘗試，prompt 描述「年輕夫妻 + 貓在中間」，跑出來只有貓單獨在沙發上。夫妻完全消失。

**真相**：
- DALL-E 3 在「主體（cute cat）+ 次要元素（人物）」競爭時，AI 偏好「單一萌主體」
- Prompt 太長（>200 字）時，前後元素互相搶權重
- 「side by side」「sitting on either side」這種空間關係 DALL-E 3 解析能力弱

**✅ 破解公式**：
**翻轉描述順序**，把要保住的元素放最前面當主角：
```
原本（貓主角）：A chubby orange tabby cat... a young couple sitting on
either side of the cat...
[結果：人消失]

改成（人主角）：A young Asian cartoon couple sitting on a sofa... between
them, a chubby orange tabby cat snuggling in the small space at their hips...
[結果：人和貓都在]
```

---

### 🚨 坑 #6：角色一致性會「漂移」

**踩坑情境**：
- 第一張角色設定：琥珀眼
- 第二張同 prompt 重產：藍眼
- 第三張：異色瞳（一藍一棕）

**真相**：
- DALL-E 3 沒有 seed 鎖定機制（即使有也常無效）
- 即使 prompt 一字不差，生成結果隨機波動
- 最容易漂移的部位：**眼睛顏色 > 鬍鬚 > 嘴型 > 體型 > 毛色**

**✅ 破解公式**：
1. **明確指定每個容易漂移的部位**：
```
warm amber eyes (NOT blue, NOT green)
細鬚 prompt：visible white whiskers
特定鼻色：pink small triangular nose
```

2. **每張新圖跟「主視覺基準圖」對比**，不像就重產
3. **接受 70-80% 一致性是免費版上限**，不要強求 100%

---

### 🚨 坑 #7：表情包式設定圖（多格）一致性差

**踩坑情境**：
產「9 格表情變化」時，9 個格子裡的貓變成「9 隻不同的貓」（臉型、毛色、風格都漂移）。

**真相**：
- DALL-E 3 處理「同一畫面多角色」時，會把每個分格當獨立創作
- 格子越多 → 一致性越差
- 單張 4 視角設定圖 OK，但 9 格表情就會炸

**✅ 破解公式**：
- **角色設定圖：4 視角是極限**（front / side / 3-quarter / back）
- **表情包：分多次產，一次只產 1-3 個表情**
- Prompt 寫 `same exact character: [完整描述], showing happy excited expression`

---

### 🚨 坑 #8：Kling 一天只有 6 段免費

**踩坑情境**：
60 秒影片需要 9-10 段動畫，Kling 免費 6 段不夠。

**✅ 破解公式**：
1. **分 2 天跑**：第一天 6 段，第二天剩下的
2. **混合 Luma**：Luma 每月 30 段免費，補 Kling 不夠的
3. **靜圖 + CapCut Ken Burns**：不重要的鏡頭用靜圖 + 推軌效果，省 Kling 額度

**靜圖可代替動畫的鏡頭類型**：
- 大廳、空景、夜景（環境鏡頭）
- 結尾品牌卡（本來就是靜態）
- 純情緒鏡頭（角色不動，靠光影變化）

**必須用 Kling/Luma 的鏡頭類型**：
- 角色動作鏡頭（蓋章、走路、伸手）
- 運鏡複雜的鏡頭（環繞、推拉）
- 表情情緒變化鏡頭

---

## 2. 🔥 破解公式速查表

### Pixar 風格強制（防止寫實漂移）

```
✅ A 3D Pixar animated movie scene, fully cartoon stylized animation
✅ NOT photorealistic
✅ All characters rendered in Pixar 3D animation style
```

### 角色一致性錨點

```
[豆豆DNA v2 完整版]：

a chubby and round orange tabby cat with cute round face,
wearing a soft blue knitted turtleneck sweater,
warm amber eyes (NOT blue),
visible white whiskers, pink small triangular nose,
soft fluffy fur with subtle orange tabby stripes,
in 3D Pixar movie style, soft volumetric lighting,
cinematic depth of field, consistent character design,
same exact cat character as reference
```

### 比例設定（Bing UI 必須切換）

```
垂直社群（IG Reels / TikTok / FB Stories）→ Bing UI 選「4:7（垂直）」
                                            → Prompt 寫 tall vertical portrait composition
                                            
橫式廣告（YouTube / 接待中心 TV）          → Bing UI 選「7:4（水平）」
                                            → Prompt 寫 wide cinematic landscape composition
                                            
方形（IG 主貼文）                          → Bing UI 選「1:1（方形）」
                                            → Prompt 寫 square balanced composition
```

### 文字渲染（永遠不要靠 AI）

```
❌ 不要寫：text reads "XXX"
✅ 改成：blank surface, text will be added in post-production
✅ CapCut 後製：用文字層 + 動畫
```

### 跨鏡頭場景連續性（同一住家）

```
所有客廳 / 居家鏡頭 prompt 都加：
wooden oak floor, beige walls, potted monstera plant in corner,
warm evening lamp light, same modern apartment interior style
```

---

## 3. 📋 標準 Prompt 模板（複製即用）

### 模板 A：純角色場景（單貓）

```
[豆豆DNA v2], [場景描述]，[動作/姿勢]，[表情], soft volumetric
lighting, cinematic depth of field, tall vertical portrait composition.
```

### 模板 B：人物 + 動物同框（已驗證可用）

```
A 3D Pixar animated movie scene, fully cartoon stylized animation, NOT
photorealistic. [人物主體描述 + Pixar 風格修飾]. Between them /
beside them, [豆豆 DNA 簡化版 with warm amber eyes]. [場景元素].
Tall vertical portrait composition, intimate cozy mood.
```

### 模板 C：物件 / 動作特寫（蓋章、翻書、走路）

```
[豆豆DNA v2], close-up of [動作描述 + 物件], [動作細節描述],
[blank surface for post-production text if needed], soft warm lighting,
clean background, tall vertical portrait composition, dynamic close-up.
```

### 模板 D：建築 / 環境鏡頭（無角色）

```
A 3D Pixar animated movie style, fully cartoon stylized rendering, NOT
photorealistic. A modern Taiwanese apartment building exterior at
[時段], warm sunlight, cinematic composition, soft shadows, animated
film aesthetic, tall vertical portrait composition.
```

### 模板 E：CTA / 結尾品牌卡（CapCut 製作，不用 Bing）

```
不需要 Bing prompt。直接在 CapCut 用：
- 純色背景 + 品牌色
- 文字工具加 logo + slogan
- 從表情包剪切角色靜態圖貼上
- 加微動畫（淡入、彈跳、放大）
```

---

## 4. 🚀 標準工作流程（從接案到交付）

### 階段 1：接案前置（30 分鐘）

```
☐ 收集建商基本資料（建案名、位置、坪數、總價、公設）
☐ 確認目標客群（首購/換屋/退休/投資客）
☐ 確認投放平台（FB/IG/YouTube/接待中心）
☐ 確認影片長度（15/30/60/90/180 秒）
☐ 確認預算 / 交期
☐ 與客戶討論「品牌調性」（豪宅/溫馨/活力/質感）
☐ 簽約（含 AI 生成示意圖、實品以契約為準等合規條款）
```

### 階段 2：腳本生成（1 小時）

```
☐ 打開 Cowork → 用「01_腳本生成範本.md」填入資料
☐ Claude 產出完整分鏡 + 所有 prompt + 配音稿 + 字幕
☐ 你檢視一遍方向對不對
```

### 階段 3：4 位 Reviewer 審查（15 分鐘）

```
☐ 跟 Claude 說「啟動 4 位 Reviewer 審查腳本」
☐ 收到綜合報告（腳本 + Prompt + 法規 + 品牌）
☐ 重點看法規 reviewer 紅旗（必修）
```

### 階段 4：腳本修正（30 分鐘 - 1 小時）

```
☐ 跟 Claude 說「修 P0 + P1，產出 v2 腳本」
☐ 拿到修正版完整腳本
☐ （可選）再跑一次 Reviewer 確認
```

### 階段 5：角色設定（30-60 分鐘）

```
☐ 打開「角色設定器」Artifact 拉選單
☐ 複製 Prompt A → 貼到 Bing（4:7 垂直）
☐ 跑 4-8 張挑最棒的「主視覺」
☐ 存到 ~/Desktop/[案名]/01_image/角色設定_主視覺.png
☐ 確認眼睛顏色、體型、衣服都符合期待
☐ 把「角色 DNA」字串複製，後續所有鏡頭都用
```

### 階段 6：產所有鏡頭關鍵畫面（1-2 小時）

```
☐ 照 v2 腳本 + 破解公式跑每個鏡頭
☐ 每張產 4-8 張挑最像主視覺的
☐ 存到 01_image/Sxx_[場景].png
☐ 不像主視覺 → 重產（眼睛、體型、毛色尤其要對）
☐ 人物鏡頭一定用三層 Pixar 強制
☐ 文字鏡頭一律空白後製
```

### 階段 7：產動畫（2-3 小時）

```
☐ Kling：每天 6 段免費，分 2 天跑
☐ Luma：補 Kling 不夠的，每月 30 段
☐ 靜圖鏡頭：CapCut Ken Burns 推軌效果
☐ 存到 02_video/Sxx.mp4
```

### 階段 8：配音（30 分鐘）

```
☐ ElevenLabs：選 Mandarin 溫暖女聲
☐ 設定：Stability 45 / Similarity 80 / Style 15
☐ 試聽幾種聲音挑最符合品牌調性
☐ 存到 03_audio/旁白.mp3
☐ Bingo：每月 10,000 字夠用 5-10 支影片
```

### 階段 9：CapCut 剪接（2 小時）

```
☐ 開新專案 → 9:16 直式（4:7 也可）
☐ 拉素材到時間軸照腳本對齊
☐ 配音音軌對齊
☐ 自動上字幕（中文）→ 修正錯字
☐ 加 BGM（搜 heartwarming/gentle piano）
☐ 加示意圖浮水印（右下角整片）
☐ 公設鏡頭加「公設示意圖」（左下角）
☐ S09 印章後製文字 + 蓋章音效
☐ 結尾合規塊（坪數、機能、個資告知）
```

### 階段 10：輸出與檢查（30 分鐘）

```
☐ 解析度 1080×1920 / 30fps / MP4
☐ 命名：[案名]_[長度]_v[版本]_[日期].mp4
☐ 自我檢查清單：
   ☐ 60 秒剛好
   ☐ 字幕無錯字
   ☐ BGM 不蓋過旁白
   ☐ 浮水印持續顯示
   ☐ 公設標註齊全
   ☐ 結尾合規塊清楚
   ☐ 在手機上預覽（IG/FB 體驗）
☐ 產出衍生短版（30 秒、15 秒）
☐ 交付給客戶（用 Google Drive 或 WeTransfer）
```

### 階段 11：客戶反饋與修正（彈性時間）

```
☐ 收到反饋 → 判斷類型：
   - 微調文案 → 30 分（重跑配音 + 字幕）
   - 換鏡頭畫面 → 1 小時（重跑 Bing + Kling）
   - 大改方向 → 重啟階段 4
☐ 修正版本命名：v2、v3...
```

---

## 5. 💎 升級判斷準則（什麼時候該付費）

| 痛點 | 升級工具 | 月費 | 痛點消除程度 |
|---|---|---|---|
| 角色每張長不一樣 | Midjourney Basic | $10 | 95%（用 `--cref`） |
| 中文字渲染失敗 | （無解，必後製）| — | 0%（任何工具都不穩） |
| Bing 慢、額度不足 | Midjourney + Leonardo | $10 + $0 | 80% |
| Kling 6 段不夠 | Kling Standard | $7 | 100% |
| 配音字數不夠 | ElevenLabs Starter | $5 | 100% |
| 商用授權不安心 | 任何付費版 | 看上面 | 100% |

**升級臨界點**：每月接 **3 支以上**建案影片時，月費 **$22**（Midjourney+Kling+ElevenLabs）開始划算。

**不需升級的情況**：
- 個人作品集 / 練手
- 一次性試水溫
- 還在驗證接案能不能成

---

## 6. 🛡️ 法規合規清單（不能省略）

### 🚨 必做（漏一個就違法）

```
☐ Slogan 不含「最」字（「最佳」「最好」「最棒」全部違法）
☐ 不用「保證」「絕對」「全台」這種絕對化用語
☐ AI 生成的「未來街景／公設／室內」標「示意圖」
☐ 距離標示 → 實測 + 加註「步行時間因人而異」
☐ 坪數總價 → 加「實際以買賣契約為準」
☐ 公設名稱 → 加「以實際完工及管委會規約為準」
☐ 周邊機能 → 不點名具體地標 / 加「因樓層座向有所差異」
☐ AI 虛構代言人 → 加「品牌虛構代言人，非實際評鑑機構」
☐ QR Code 連 LINE → 加好友後立即顯示個資蒐集告知
☐ 整片右下角全程「3D 示意圖｜實品以建造完成後為準」
```

### ⚠️ 強烈建議

```
☐ 與客戶簽約時白紙黑字寫「廣告為示意圖、實品以契約為準」
☐ 客戶提供的所有數據（坪數、總價、距離）要對齊建照、規約
☐ 投放前讓客戶法務看過簽核
☐ 保留所有 AI 生成原始檔（公平會調查時可舉證）
```

### 📜 違反時可能的後果

- 公平交易法 §21 違反 → 5 萬至 2,500 萬罰鍰
- 消保法 §22 違反 → 罰鍰 + 消費者求償
- 廣告代理商與建商**連帶責任**

---

## 🎯 結語：從測試到接案的心法

**測試階段你已經學到的**（2026-04-29）：
1. ✅ 整個系統流程能跑通
2. ✅ 角色設定 → 腳本 → Review → 修正 → 素材生產的順序合理
3. ✅ Pixar 三層強制公式有效（破解最大難關）
4. ✅ 免費版能達到 70-80 分品質
5. ✅ 法規 reviewer 是不可或缺的最後防線

**下次接案時你應該做的**：
1. 直接打開「01_腳本生成範本.md」填客戶資料
2. 跟 Claude 說「啟動 4 位 Reviewer」
3. 套用本檔的破解公式產素材
4. 套用本檔的合規清單交付

**真正的價值不是這支影片做得多好，是你建立了「重複量產建案 AI 影片」的系統。** 下次再接案，可以從 6 小時壓到 3 小時。第三、四個案子之後，可能 2 小時就能交付一支高品質影片。

這就是**系統化接案** vs **單發案接案**的差別。

---

## 📞 卡關時直接問 Claude

```
「我在 Sxx 鏡頭翻車了，[描述問題]」
「客戶要求改 [內容]」
「Bing 拒絕生成 [描述]」
「角色不一致怎麼辦」
「法規這部分不確定怎麼改」
```

每次的回饋都會更新到這份 SOP，**這份檔案會越來越強**。

