# Multi-site CMS Plan

## Stack

- Laravel 10
- Vue 3 + Inertia
- Filament 3
- spatie/laravel-permission

## Roles

- `super_admin`
  - 管全部網站
  - 可新增網站與指派帳號
- `site_admin`
  - 只管被授權的網站
  - 可管理最新消息、建案、工程進度、聯絡訊息

## Core tables

- `sites`
- `site_domains`
- `site_settings`
- `site_user`
- `news_articles`
- `projects`
- `progress_updates`
- `contact_messages`

所有內容表都會掛 `site_id`，後台查詢也依登入帳號自動限制站台範圍。

## Open a new site

1. Super admin 在後台建立 `Site`
2. 填入 `primary_domain`、`theme_key`、品牌資料與模組開關
3. 建立 `site_domains`
4. 指派 `site_admin` 到新站
5. 建立首頁設定、建案、最新消息、工程進度
6. 網域指到同一套應用後，由 `ResolveCurrentSite` 依 host 判斷站台

## Current implementation scope

- 前台首頁已改為依站台資料渲染
- Filament 已有 5 個核心資源：
  - 網站管理
  - 最新消息
  - 建案管理
  - 工程進度
  - 聯絡訊息
- Seeder 會建立：
  - `superadmin@example.com / password`
  - `siteadmin@example.com / password`
  - 1 個示範站台與示範內容

## Next recommended steps

1. 補 `SiteSetting` 的 Filament 編輯頁
2. 新增媒體上傳與檔案管理
3. 補前台列表頁 / 內頁 / 聯絡表單送出流程
4. 如果環境可升級到 PHP 8.2+，再升到 Laravel 11/12 與新版套件
