# AI Video — 建案影片自動化工具

Operator 填表 + 4 次核准，系統自動生成圖片、動畫、配音、剪接成品。

## 技術棧

- **後端**: Laravel 10 + PHP 8.3
- **前端**: Vue 3 SPA + Tailwind CSS
- **Queue**: Redis + Horizon
- **DB**: MySQL 8
- **容器**: Docker Compose

## 外部服務

| 服務 | 用途 | 費用 |
|------|------|------|
| fal.ai Flux Kontext | 圖片生成（角色一致性） | ~$0.04/張 |
| Kling AI API | 圖轉影片 | ~$0.21/5s |
| Azure TTS | 配音（zh-TW 台灣腔） | 免費 50 萬字/月 |
| Remotion Lambda | 影片自動剪接 | ~$0.05/支 |

**每支影片約 $4.8，無月費。**

## 快速開始

```bash
# 1. Clone
git clone https://github.com/y2024933/aivideo.git
cd aivideo

# 2. 複製環境變數
cp .env.example .env
cp .env.example .env.docker

# 3. 啟動 Docker
docker compose up -d

# 4. 安裝依賴 + 遷移 + Seed
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

# 5. 安裝前端
docker compose exec vite npm install

# 6. 開啟瀏覽器
open http://localhost:8010
```

## 預設帳號

```
Email: admin@aivideo.local
密碼:  password
```

## Docker 服務

| 服務 | Port | 用途 |
|------|------|------|
| web | 8010 | Nginx |
| vite | 5174 | HMR dev server |
| mysql | 3308 | MySQL |
| redis | 6380 | Redis |
| phpmyadmin | 8083 | DB GUI |

## API 路由

```
POST   /api/login                              登入
POST   /api/logout                             登出
GET    /api/user                               取得當前用戶

GET    /api/cases                              建案列表
POST   /api/cases                              建立建案
GET    /api/cases/{id}                         查看建案
POST   /api/cases/{id}/generate-characters     生成角色預覽
POST   /api/cases/{id}/approve-character       核准角色
POST   /api/cases/{id}/generate-scenes         生成場景圖
POST   /api/cases/{id}/approve-images          核准圖片 → 開始生成動畫
POST   /api/cases/{id}/generate-voiceover      生成配音
POST   /api/cases/{id}/render-video            渲染最終影片
GET    /api/health                             健康檢查
```

## Wizard 流程

```
1. 填表 + 貼腳本  →  建立建案 + shots
2. 生成角色預覽    →  Flux Kontext 產 4 張 → 挑 1 張核准
3. 確認腳本       →  檢視分鏡內容
4. 生成場景圖     →  Flux Kontext 用核准角色 → 9 張 → 核准
5. 生成動畫+配音  →  Kling API + Azure TTS → 自動輪詢
6. 渲染成品       →  Remotion Lambda 自動剪接 → 下載
```

## 環境變數

```bash
# API 模式（false = stub 開發用）
APP_USE_REAL_APIS=false

# fal.ai
FAL_API_KEY=

# Kling AI
KLING_ACCESS_KEY=
KLING_SECRET_KEY=

# Azure TTS
AZURE_TTS_KEY=
AZURE_TTS_REGION=eastasia

# AWS (Remotion Lambda)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
REMOTION_FUNCTION_NAME=
REMOTION_SERVE_URL=
REMOTION_REGION=us-east-1
```

## 測試

```bash
docker compose exec app ./vendor/bin/pest
```

## 參考文件

- `09_腳本參考/CLAUDE_CODE_BRIEF.md` — 完整專案 Brief
- `09_腳本參考/07_松韻苑_修正版腳本_v2.md` — 範例腳本
- `09_腳本參考/09_接案SOP_v2.0_踩坑筆記.md` — 踩坑筆記
