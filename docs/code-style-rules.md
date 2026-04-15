# 代碼風格規範

## 適用範圍

本規範適用於專案的 HTML、CSS、JavaScript 代碼撰寫。
所有新增或修改的代碼應遵循以下風格，確保與既有程式碼一致。

---

## 1. 命名規範

### 1.1 HTML

- id / class：使用 kebab-case（如 `site-header`、`menu-trigger`、`home-header-overlay`）
- 語意化命名，避免用途不明的名稱（如 `div1`、`box2`）

### 1.2 CSS

- class 命名：kebab-case，語意化描述用途
- 避免過深的巢狀選擇器（建議不超過 3 層）
- 顏色、間距等可復用的值考慮使用 CSS 自訂屬性（`--變數名`）

### 1.3 JavaScript

- 變數 / 函式：camelCase（如 `menuTrigger`、`handleClick`）
- 常數：UPPER_SNAKE_CASE（如 `MAX_RETRY`、`ANIMATION_DURATION`）
- DOM 元素變數建議加前綴或後綴（如 `$menuBtn` 或 `menuEl`）

---

## 2. HTML 結構

### 2.1 頁面基本結構

```html
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>頁面標題</title>
  <meta name="description" content="頁面描述">
  <!-- 字型 -->
  <!-- 樣式 -->
</head>
<body>
  <header class="site-header">...</header>
  <main>...</main>
  <footer class="site-footer">...</footer>
  <!-- 腳本 -->
</body>
</html>
```

### 2.2 語意化標籤

- `<header>` — 頁首
- `<nav>` — 導覽列
- `<main>` — 主要內容
- `<section>` — 內容區塊
- `<article>` — 獨立內容
- `<footer>` — 頁尾

---

## 3. CSS 規範

### 3.1 檔案組織

全站使用單一 `style.css`，按區塊組織：

```css
/* ===== Reset / Base ===== */
/* ===== Layout ===== */
/* ===== Header ===== */
/* ===== Navigation ===== */
/* ===== Hero ===== */
/* ===== Sections ===== */
/* ===== Footer ===== */
/* ===== Components ===== */
/* ===== Utilities ===== */
/* ===== Media Queries ===== */
```

### 3.2 RWD

- 以 media query 處理響應式設計
- 測試桌面版與手機版的顯示效果
- 斷點依設計稿需求設定

---

## 4. JavaScript 規範

### 4.1 檔案組織

全站使用單一 `main.js`，原生 JS，不依賴外部函式庫。

### 4.2 事件處理

```javascript
// 使用 addEventListener
document.querySelector('.menu-trigger').addEventListener('click', function() {
  // 處理邏輯
});

// DOMContentLoaded 確保 DOM 載入完成
document.addEventListener('DOMContentLoaded', function() {
  // 初始化邏輯
});
```

### 4.3 避免事項

- 避免使用 `var`，優先使用 `const`，需要重新賦值時用 `let`
- 避免在全域範圍宣告過多變數
- 避免 inline event handler（如 `onclick="..."`）

---

## 5. 註解風格

### 5.1 語言使用

- class / id / 變數名：英文
- 註解說明：繁體中文
- 頁面文案內容：繁體中文

### 5.2 CSS 區塊註解

```css
/* ===== Header ===== */
.site-header { ... }

/* 手機版漢堡選單 */
.menu-trigger { ... }
```

### 5.3 JS 註解

```javascript
// 漢堡選單開關
function toggleMenu() { ... }
```

---

## 6. 圖片與素材

- 圖片放在 `assets/media/`
- 檔名使用 kebab-case（如 `hero-placeholder.mp4`）
- 圖片應適當壓縮，避免過大檔案影響載入速度
- 使用適當的圖片格式（照片用 WebP/JPG，圖示用 SVG/PNG）
