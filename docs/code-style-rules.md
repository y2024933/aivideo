# 代碼風格規範

## 適用範圍

本規範適用於專案的 PHP（Laravel）、Vue 3、Tailwind CSS 代碼撰寫。
所有新增或修改的代碼應遵循以下風格，確保與既有程式碼一致。

---

## 1. 命名規範

### 1.1 PHP / Laravel

- 類別名：PascalCase（如 `SiteController`、`ProjectResource`）
- 方法 / 變數：camelCase（如 `getSiteSettings`、`$currentSite`）
- 常數：UPPER_SNAKE_CASE（如 `MAX_UPLOAD_SIZE`）
- Migration / config / route 檔名：snake_case
- Model 屬性：snake_case（對應資料庫欄位）

### 1.2 Vue / JavaScript

- 組件檔名：PascalCase（如 `SiteLayout.vue`、`ProjectCard.vue`）
- 變數 / 函式：camelCase（如 `menuOpen`、`toggleMenu`）
- 常數：UPPER_SNAKE_CASE
- Props：camelCase（如 `routeMap`、`isPreview`）
- Emits：kebab-case（如 `update:modelValue`）

### 1.3 CSS

- Tailwind utility class 優先
- 自訂 class：kebab-case（如 `site-header`、`classic-footer`）
- CSS 變數：`--site-primary`、`--site-secondary`

---

## 2. PHP / Laravel 規範

### 2.1 Controller

- Web Controller 優先使用 Form Request 驗證
- Inertia 頁面回傳 `Inertia::render()` 或 `redirect()`
- Filament Resource / Action 依 Filament 既有模式處理

### 2.2 Eloquent

- 用 `with()` 預載關聯，避免 N+1
- 批次更新用 `update()` 或 `upsert()`
- 查詢條件善用 scope

### 2.3 Migration

- 新增欄位應考慮既有資料遷移，必要時用 nullable / default
- 提供 `down()` 方法
- 欄位位置用 `after()` 保持邏輯順序

---

## 3. Vue 3 規範

### 3.1 組件結構

```vue
<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
});
</script>

<template>
    <!-- 模板內容 -->
</template>

<style scoped>
/* 僅在必要時使用 scoped style */
</style>
```

### 3.2 重點原則

- 使用 `<script setup>` 語法
- `defineProps` / `defineEmits` 按需使用
- 善用 computed 與 composable 抽共用邏輯
- 媒體路徑透過 `useMedia()` 的 `mediaUrl()` 取得
- Inertia Head 管理頁面 title / meta

---

## 4. CSS / Tailwind 規範

### 4.1 優先順序

1. Tailwind utility class（優先）
2. 沿用現有 `resources/css/app.css` 的自訂樣式
3. 組件 `<style scoped>`（跨頁共用樣式不要用 scoped）

### 4.2 RWD

- Tailwind 的 responsive prefix（`md:`, `lg:` 等）
- 測試手機版與桌面版的顯示效果

---

## 5. 註解風格

### 5.1 語言使用

- class / id / 變數名：英文
- 註解說明：繁體中文
- 頁面文案內容：繁體中文

### 5.2 PHP 註解

```php
// 取得目前站台設定
$settings = $site->setting;
```

### 5.3 Vue 註解

```vue
<!-- 手機版漢堡選單 -->
<button class="mobile-trigger" @click="toggleMenu">
```

---

## 6. 檔案與素材

- 上傳檔案透過 Filament FileUpload，存放於 `storage/app/public/`
- 檔名使用 kebab-case
- 圖片應適當壓縮，使用適當格式（照片 WebP/JPG，圖示 SVG/PNG）
