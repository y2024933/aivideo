# Multi-site CMS Architecture

## Stack

- PHP 8.3 + Laravel 12
- Vue 3.4 + Inertia.js 2
- Filament 3
- Tailwind CSS + Vite 5
- spatie/laravel-permission
- Docker Compose 開發環境

## Roles

- `super_admin`
  - 管全部網站
  - 可新增網站與指派帳號
  - 可存取 SiteResource、SiteSettingResource
- `site_admin`
  - 只管被授權的網站
  - 可管理最新消息、建案、工程進度、聯絡訊息

## Core tables

- `sites` — 站台基本資料（名稱、品牌、Logo、Favicon、主題色）
- `site_domains` — 站台網域（一對多，由 ResolveCurrentSite middleware 解析）
- `site_settings` — 站台設定（JSON 欄位：首頁區塊、Hero、SEO、頁尾、社群連結、追蹤碼）
- `site_user` — 站台與使用者多對多關聯
- `projects` — 建案作品
- `news_articles` — 最新消息
- `progress_updates` — 工程進度
- `progress_albums` — 工程進度相簿
- `contact_messages` — 聯絡訊息
- `pages` — 自訂頁面
- `media_assets` — 媒體資源
- `navigation_items` — 導覽項目

所有內容表都掛 `site_id`，後台查詢依登入帳號自動限制站台範圍。

## Site resolution flow

1. Request 進入
2. `ResolveCurrentSite` middleware 用 `$request->getHost()` 查 `site_domains` 表
3. 找到對應 Site → 存入 `$request->attributes->set('currentSite', $site)`
4. `HandleInertiaRequests` 把 site 物件 share 給前端
5. `SiteLayout.vue` 依 site 資料渲染主題、顏色、Logo、Favicon、追蹤碼

預覽模式：`/preview/{site:slug}/...` 可不經網域解析直接指定站台。

## Theme system

目前支援兩個主題，由 `sites.theme_key` 控制：

- `builder-classic`（預設）— 白底兩層式 Header，深色 Footer
- `builder-editorial` — 文藝風，淡色系背景

主題色透過 CSS 變數注入：`--site-primary`、`--site-secondary`

## Open a new site

1. Super admin 在後台建立 Site（填入名稱、品牌、主題、顏色、Logo、Favicon）
2. 建立 site_domains（指定網域）
3. 建立 SiteSetting（首頁區塊、Hero 設定、SEO、頁尾、追蹤碼）
4. 指派 site_admin 到新站
5. 建立建案、最新消息、工程進度等內容
6. 網域 DNS 指到同一套應用，由 ResolveCurrentSite 自動判斷站台

## Current implementation

### 已完成
- 前台所有頁面：首頁、關於、建案（列表+內頁）、最新消息（列表+內頁）、多元服務、工程進度、聯絡我們
- 後台 10 個 Filament Resource
- SiteSetting 完整編輯（首頁模組、Hero、關於、多元服務、聯絡、SEO、頁尾、社群、追蹤碼、轉換工具）
- 媒體上傳（FileUpload + public disk）
- 兩套主題（classic / editorial）
- Docker 開發環境
- Playwright E2E 測試框架

### 可繼續發展
- 更多主題樣式
- 前台搜尋功能
- SEO sitemap 自動生成
- 多語系支援
