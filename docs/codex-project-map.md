# Project Map

> 目的：提供一份經實際目錄掃描驗證的專案地圖，降低重新摸索成本。
>
> 更新日期：2026-04-18

---

## 1. 已驗證的基本事實

- 專案類型：Laravel 多站台建設公司形象網站 CMS
- PHP：8.3
- Laravel：12
- Filament：3（後台管理面板）
- Vue：3.4（Composition API + `<script setup>`）
- Inertia.js：2（前後端橋接）
- Tailwind CSS（utility-first 樣式）
- Vite：5（前端建置）
- 開發環境：Docker Compose
- 權限：spatie/laravel-permission

主要依據：`composer.json`、`package.json`、`docker-compose.yml`

---

## 2. 專案量體

以下數字來自 2026-04-18 實際掃描：

- Model：12
- Filament Resource：10
- Controller：5（SiteController 為前台核心）
- Migration：29
- Vue Pages：10（前台）
- Layouts：3
- Composables：1（useMedia）

結論：中小型專案，架構清晰，可快速掌握全貌。

---

## 3. 核心架構：多站台

### 3.1 站台資料結構

```
sites                    # 站台基本資料（名稱、品牌、Logo、Favicon、主題、顏色）
  ├── site_domains       # 站台網域（一對多）
  ├── site_settings      # 站台設定（首頁區塊、Hero、SEO、頁尾、社群、追蹤碼）
  ├── site_user          # 站台與使用者關聯（多對多）
  ├── projects           # 建案作品
  ├── news_articles      # 最新消息
  ├── progress_updates   # 工程進度
  ├── progress_albums    # 工程進度相簿
  ├── contact_messages   # 聯絡訊息
  ├── pages              # 自訂頁面
  ├── media_assets       # 媒體資源
  └── navigation_items   # 導覽項目
```

所有內容表都掛 `site_id`，後台查詢依登入帳號自動限制站台範圍。

### 3.2 站台解析流程

1. Request 進入 → `ResolveCurrentSite` middleware 依 host 查 `site_domains` 表
2. 找到對應 `Site` → 存入 `$request->attributes`
3. `HandleInertiaRequests` 把 site 資料 share 給前端
4. 前台 `SiteLayout.vue` 依 site 資料渲染主題、顏色、Logo、Favicon

預覽模式：`/preview/{site:slug}/...` 路由可不經網域直接指定站台。

### 3.3 權限角色

- `super_admin`：管全部網站，可新增站台與指派帳號
- `site_admin`：只管被授權的網站內容

---

## 4. 前台頁面結構

所有前台路由由 `SiteController` 處理，Vue 頁面在 `resources/js/Pages/Site/`：

| 路由 | Controller 方法 | Vue 頁面 |
|------|----------------|----------|
| `/` | `home` | `Home.vue` |
| `/about` | `about` | `About.vue` |
| `/projects` | `projects` | `Projects/Index.vue` |
| `/projects/{slug}` | `projectShow` | `Projects/Show.vue` |
| `/news` | `news` | `News/Index.vue` |
| `/news/{slug}` | `newsShow` | `News/Show.vue` |
| `/services` | `services` | `Services.vue` |
| `/progress` | `progress` | `Progress.vue` |
| `/progress/album/{album}` | `progressAlbum` | `ProgressAlbum.vue` |
| `/contact` | `contact` / `submitContact` | `Contact.vue` |

共用 Layout：`resources/js/Layouts/SiteLayout.vue`
- 支援兩個主題：`builder-classic`（預設）與 `builder-editorial`
- 主題色透過 CSS 變數 `--site-primary` / `--site-secondary` 注入

---

## 5. 後台 Filament Resources

| Resource | 對應 Model | 功能 |
|----------|-----------|------|
| `SiteResource` | Site | 站台管理（SuperAdmin 限定） |
| `SiteSettingResource` | SiteSetting | 站台設定（首頁、Hero、SEO、頁尾、追蹤碼） |
| `ProjectResource` | Project | 建案管理 |
| `NewsArticleResource` | NewsArticle | 最新消息 |
| `ProgressUpdateResource` | ProgressUpdate | 工程進度 |
| `ProgressAlbumResource` | ProgressAlbum | 工程進度相簿 |
| `ContactMessageResource` | ContactMessage | 聯絡訊息 |
| `PageResource` | Page | 自訂頁面 |
| `MediaAssetResource` | MediaAsset | 媒體資源 |
| `NavigationItemResource` | NavigationItem | 導覽項目 |

---

## 6. Docker 開發環境

| 服務 | 容器名稱 | 用途 | 本機 Port |
|------|---------|------|-----------|
| app | `multisite-cms-app` | PHP-FPM（Laravel） | - |
| web | `multisite-cms-web` | Nginx | 8000 |
| vite | `multisite-cms-vite` | Node 20 + Vite dev server | 5173 |
| mysql | `multisite-cms-mysql` | MySQL 8.4 | 3307 |
| mailpit | `multisite-cms-mailpit` | 郵件測試 | 8025 |
| phpmyadmin | `multisite-cms-phpmyadmin` | 資料庫管理 | 8082 |

常用指令：
```bash
# PHP / artisan / composer
docker exec -it multisite-cms-app php artisan migrate
docker exec -it multisite-cms-app php artisan db:seed
docker exec -it multisite-cms-app composer install

# Node / npm
docker exec -it multisite-cms-vite npm install
docker exec -it multisite-cms-vite npm run build

# Playwright 測試
docker exec -it multisite-cms-vite sh -c "cd tests/playwright && npx playwright test"
```

本機不裝 vendor / node_modules，全部在容器內。

---

## 7. 關鍵 Middleware

| Middleware | 功能 |
|-----------|------|
| `ResolveCurrentSite` | 依 host 解析目前站台 |
| `HandleInertiaRequests` | 共享 site、auth、flash 給前端 |
| `Authenticate` | 驗證登入 |

---

## 8. 關鍵 Composables

| Composable | 功能 |
|-----------|------|
| `useMedia()` | 提供 `mediaUrl()` 將 storage path 轉為可存取的 URL |

---

## 9. 測試

- Playwright E2E 測試：`tests/playwright/`
- 設定檔：`tests/playwright/playwright.config.js`
- baseURL：`http://localhost:8000`

---

## 10. 開工建議

收到需求時，優先這樣切入：

1. 先判斷影響層：Model → Migration → Filament Resource → Controller → Vue Page
2. 多站台相關 → 檢查 Site / SiteSetting / SiteDomain
3. 前台頁面 → 從 `SiteController` 對應方法往 Vue 頁面追
4. 後台功能 → 從 Filament Resource 往 Model 追
5. 樣式 / RWD → `SiteLayout.vue` + Tailwind class
