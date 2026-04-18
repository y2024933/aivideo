# Commit 規範

## 規則

- 當使用者要求「生成 commit 訊息」時，**只生成文字，不執行 git commit**
- 每次提供 **三個版本** 供使用者選擇
- 格式固定為：`[type] 中文描述`
- 描述下方可附修改內容，以 `-` 標注，可分行
- commit 標題應簡潔明確，避免過長或過度籠統
- 優先描述本次修改的主要目的，而非所有細節

---

## 允許的 type

| type | 用途 |
|------|------|
| `feat` | 新增功能 |
| `fix` | 修復錯誤 |
| `refactor` | 重構（不影響功能） |
| `docs` | 文件變更 |
| `chore` | 雜項維護 |
| `perf` | 效能優化 |
| `style` | 樣式或程式碼格式調整（不影響功能） |

---

## 範例

### 新增功能

**版本 1**
```
[feat] 新增 Favicon 上傳功能

- sites 表新增 favicon_path 欄位
- SiteResource 加入 Favicon FileUpload
- SiteLayout.vue 自動輸出 link rel="icon"
```

**版本 2**
```
[feat] 後台支援 Favicon 設定與前台自動載入
```

**版本 3**
```
[feat] 站台 Favicon 功能
```

### 修復錯誤

**版本 1**
```
[fix] 修正多站台資料隔離問題

- getEloquentQuery 加入 site_id 過濾
- 修正 site_admin 可看到其他站台資料
```

**版本 2**
```
[fix] 修正後台資料未正確隔離站台
```

**版本 3**
```
[fix] 多站台權限修復
```

### 重構

**版本 1**
```
[refactor] 更新 agent 定義與 docs 至 Laravel + Vue 技術棧

- 6 個 agent 從靜態 HTML 規則改為 Laravel/Filament/Vue
- docs 同步更新（code-style、workflow、review）
```

**版本 2**
```
[refactor] agent 與 docs 對齊目前 Laravel + Vue 架構
```

**版本 3**
```
[docs] 全面更新開發文件與 agent 定義
```

---

## 注意事項

### 1. 描述應具體明確

不好：`[feat] 更新頁面` / `[fix] 修正問題`
好：`[feat] 新增工程進度相簿功能` / `[fix] 修正首頁 Hero 圖片手機版溢出`

### 2. 依據修改範圍選擇描述層級

**小範圍**（單一檔案）：
```
[fix] 修正 SiteResource favicon FileUpload 缺少 imageEditor
```

**中範圍**（多個檔案）：
```
[feat] 新增站台追蹤碼設定

- SiteSetting 加入 GA4、GTM、Meta Pixel、LINE Tag 欄位
- SiteLayout.vue 自動注入追蹤碼
```

**大範圍**（跨後端 + 前端）：
```
[feat] 實作多站台聯絡表單

- 新增 ContactMessage Model + Migration
- 新增 Filament ContactMessageResource
- SiteController 加入 submitContact
- 前台 Contact.vue 表單驗證與送出
```
