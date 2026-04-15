# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 重要規則

- **使用繁體中文回覆**。
- **簡潔高效的程式碼**：優先用簡潔寫法，一行能解決的不要拆成多行，避免不必要的中間變數。
- **維持頁面風格一致**：所有頁面共用相同的 header/footer/nav 結構，修改時需同步所有 `.html` 檔。
- **不破壞 RWD**：任何樣式修改都要確認手機版與桌面版的顯示效果。

## 專案概覽

泉宇建設（CHYUAN YEU）/ 承岳建築 形象網站。純靜態站，無框架、無建置工具。

- 語言：HTML5、CSS3、原生 JavaScript
- 字型：Noto Serif TC + Manrope（Google Fonts）
- 架構：多頁式（非 SPA）

## 檔案結構

```
index.html          # 首頁
about.html          # 關於我們
projects.html       # 建案作品
progress.html       # 工程進度
news.html           # 最新消息
contact.html        # 聯絡我們
assets/
  css/style.css     # 全站樣式
  js/main.js        # 全站互動邏輯
  media/            # 影片、圖片素材
```

## 技術注意事項

### 共用元件同步

Header、Footer、Navigation 直接寫在每個 HTML 檔中（無 template engine），修改導覽列或頁尾時必須同步更新所有 6 個 HTML 檔。

### CSS 慣例

- 單一 `style.css` 管理全站樣式
- 使用語意化 class 命名（如 `site-header`、`home-header-overlay`）
- RWD 以 media query 實作

### JavaScript 慣例

- 單一 `main.js` 管理全站互動
- 原生 JS，不依賴 jQuery 或其他函式庫
- 漢堡選單使用 `menu-trigger` button 控制

### 註解語言

- class / id / 變數名：英文
- 註解說明、文案內容：繁體中文

## 參考文件（不自動載入，需要時 Read）

- `docs/workflow-rules.md` — 需求分析流程、修改建議原則
- `docs/review-rules.md` — Review 檢查清單
- `docs/code-style-rules.md` — 命名規範、HTML/CSS/JS 風格
- `docs/maintenance-rules.md` — 文件維護、FAQ、踩坑紀錄
- `docs/commit-rules.md` — Commit 訊息格式與範例
