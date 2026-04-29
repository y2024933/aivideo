# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 重要規則

- **使用繁體中文回覆**。
- **簡潔高效的程式碼**：優先用簡潔寫法，一行能解決的不要拆成多行，避免不必要的中間變數。
- `declare(strict_types=1)` 在每個 PHP 檔案頂部。
- `final class` 除非有繼承需求。
- Vue: Composition API with `<script setup>`。

## 專案概覽

建案 AI 影片自動化工具。Operator 填表 + 4 次 checkpoint 核准，系統自動生成圖片、動畫、配音、剪接成品。

- 後端：Laravel 10 + PHP 8.3
- 前端：Vue 3 SPA（非 Inertia）+ Tailwind CSS + Headless UI
- Queue：Redis + Horizon
- WebSocket：Reverb + Echo
- DB：MySQL 8（Docker）
- 測試：Pest

## 架構

```
app/
  Enums/CaseStatus.php          # 建案狀態機
  Models/                        # BuildingCase, Shot, CharacterOption, Voiceover, CaseStatusHistory
  Services/
    Contracts/                   # ImageGeneratorContract, VideoGeneratorContract, TtsContract, VideoEditorContract
    Stubs/                       # Stub 實作（開發用）
    FalKontextImageGenerator.php # fal.ai Flux Kontext（圖片）
    KlingVideoGenerator.php      # Kling API（動畫）
    AzureTts.php                 # Azure TTS（配音，zh-TW）
    RemotionVideoEditor.php      # Remotion Lambda（剪接）
  Jobs/                          # 異步任務
  Http/Controllers/Api/          # API endpoints
resources/
  js/
    App.vue                      # SPA root
    stores/                      # Pinia stores
    pages/                       # Wizard 頁面
docker/                          # PHP + Nginx + Docker configs
09_腳本參考/                      # 範例腳本、Review 報告、SOP
```

## Docker 環境

```bash
docker compose up -d              # 啟動所有服務
docker compose exec app php artisan ...  # 執行 artisan 指令
docker compose exec app ./vendor/bin/pest  # 跑測試
```

| 服務 | Port | 用途 |
|------|------|------|
| web | 8010 | Nginx |
| vite | 5174 | HMR dev server |
| mysql | 3308 | MySQL |
| redis | 6380 | Redis |
| phpmyadmin | 8083 | DB GUI |

## API 切換

`APP_USE_REAL_APIS=false`（預設）使用 Stub，不呼叫真實 API。
`APP_USE_REAL_APIS=true` 時呼叫真實 API（需設定對應 key）。

## 外部服務

| 服務 | 用途 | 費用 |
|------|------|------|
| fal.ai Flux Kontext | 圖片生成（角色一致性） | $0.04/張 |
| Kling API V2.5 Turbo | 圖轉影片 | $0.21/5s |
| Azure TTS | 配音（zh-TW 台灣腔） | 免費 50 萬字/月 |
| Remotion Lambda | 影片自動剪接 | ~$0.05/支 |

## 參考文件

- `09_腳本參考/CLAUDE_CODE_BRIEF.md` — 完整專案 Brief
- `09_腳本參考/02_Reviewers.md` — 4 位 Reviewer 系統 prompt
- `09_腳本參考/07_松韻苑_修正版腳本_v2.md` — 範例腳本
- `09_腳本參考/09_接案SOP_v2.0_踩坑筆記.md` — 踩坑筆記
