# Review 規則

## Review 原則

### 1. 符合既有專案風格
- 檢查命名規則是否一致（PHP / Vue / CSS）
- 檢查檔案組織是否正確
- 檢查是否沿用專案既有的組件、composable、Resource 模式

### 2. 維持現有架構
- 新增功能是否放在正確的位置
- 後端是否遵循 Laravel / Filament 慣例
- 前端是否遵循 Vue 3 Composition API + Inertia 模式

### 3. 優先沿用現有實作
- 是否重複造輪子（已有類似組件或 composable）
- 是否有現成的 Resource 模式可參考

---

## Review 檢查重點

### 1. Laravel 後端
- [ ] Model `$fillable` / `$casts` 是否與 Migration 一致
- [ ] Eloquent 查詢是否有 N+1 問題
- [ ] Controller 驗證方式是否適當
- [ ] 路由與 middleware 是否正確
- [ ] 權限控制是否正確（多站台隔離）
- [ ] `env()` 是否只在 config 檔中使用

### 2. Filament 後台
- [ ] Resource 表單是否與 Model 一致
- [ ] FileUpload 設定是否統一
- [ ] `getEloquentQuery()` 是否正確過濾資料

### 3. Vue 前端
- [ ] `<script setup>` 語法是否正確
- [ ] Props 是否與後端傳值一致
- [ ] Inertia Head 是否正確設定
- [ ] 是否有未使用的 import 或變數
- [ ] `mediaUrl()` 是否正確使用

### 4. CSS / RWD
- [ ] Tailwind class 使用是否合理
- [ ] 手機版與桌面版是否正常
- [ ] 主題色變數是否與現有頁面一致

### 5. Migration
- [ ] 欄位設計是否考慮既有資料
- [ ] `down()` 方法是否正確
- [ ] 欄位位置是否合理

### 6. 安全性
- [ ] 外部連結是否使用 `rel="noopener noreferrer"`
- [ ] 使用者輸入是否有驗證
- [ ] 檔案上傳是否有型別與大小限制

---

## Review 輸出格式

```
## Review 結果

### 通過
- [項目描述]

### 需修正
- [檔案:行號] 問題描述 → 建議修正方式

### 建議
- [非必要但建議改善的項目]
```
