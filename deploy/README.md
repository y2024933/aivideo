# 混合部署：生產 VPS + Mac 瀏覽器端

> ⚠️ 這份文件裡的所有系統層指令（`tailscale`、`pmset`、`launchctl`、`ufw`）都必須
> **由 Paul 在自己的機器上手動執行**。專案的程式碼與 CI 不會、也不該自動跑它們。

---

## 0. 為什麼要兩台機器

| | 生產 VPS | Paul 的 Mac |
|---|---|---|
| 規格 | 1 CPU / 2GB RAM | 足以跑 Chromium |
| 跑什麼 | Laravel / Filament / 排程 / MySQL / Redis / S3 上傳 | `browser` 容器 + `queue:work --queue=browser` |
| 對外 IP | 機房 IP（雲端 ASN） | **台灣住宅 IP** |

兩個理由，缺一不可：

**1. 記憶體：生產機物理上塞不下瀏覽器。**
實測 Chromium 載入 `shopee.tw` 峰值 RSS **2137 MB**，連 `about:blank` 的地板值都是
**865 MB**。省記憶體旗標（`--disable-dev-shm-usage` 等）全開只降 7%，
`--single-process` 會直接崩。那台機器已經跑 MySQL + Redis + PHP-FPM + Horizon，
再開 Chromium 就是 OOM killer 隨機殺一個 —— 而最先被殺的通常是 MySQL。

**2. 風控：Mac 的 IP 與指紋是雲端買不到的。**
Paul 本來就在那台機器上登入蝦皮。從風控角度看，自動化流量和他平常的人為流量
**同 IP、同 profile、同指紋、同時段**。機房 IP + 全新 profile 是最容易被標記的組合，
而蝦皮封的是帳號，恢復只能靠人工 SMS OTP。

**代價：** Mac 會關機、會睡眠、會換網路。所以需要心跳（`browser:heartbeat`）、
需要回收機制（`browser:reap-stale`）、需要一個讓 operator 看得懂「現在是哪一種
壞法」的儀表（`BrowserHealthWidget`）。這三樣就是 P9 的全部內容。

---

## 1. Tailscale（一次性，兩台都要做）

Tailscale 是 WireGuard 的託管版，提供 `100.64.0.0/10` 的私有網段。
選它而不是「MySQL / Redis 開公網 + 防火牆白名單」的理由：Mac 的住宅 IP 是動態的，
白名單會每隔幾天就失效，而失效的症狀是「worker 安靜地連不上 DB」。

### 1.1 安裝

```bash
# 生產 VPS（Debian / Ubuntu）
curl -fsSL https://tailscale.com/install.sh | sh
sudo tailscale up --ssh --advertise-tags=tag:aivideo-prod

# Mac
brew install --cask tailscale
# 或從 App Store 安裝，然後在 GUI 登入
sudo tailscale up --advertise-tags=tag:aivideo-mac
```

### 1.2 記下 IP（⚠️ 後面全部用 IP，不用 MagicDNS 名稱）

```bash
tailscale ip -4                    # 看自己的
tailscale status                   # 看整個 tailnet
```

> ⚠️⚠️ **不要在 `.env.docker` 裡填 MagicDNS 名稱**（`vps.tailnet-xxxx.ts.net`）。
> `DB_HOST` / `REDIS_HOST` 是被 **Docker 容器裡的 PHP** 讀取的，而 Docker Desktop
> 容器用的是 Docker 內建 DNS（`127.0.0.11`）→ 轉送到 Docker VM 的 resolver，
> 那條路徑上沒有 Tailscale 的 MagicDNS resolver。
>
> 症狀極具誤導性：Mac 的 terminal 上 `ping vps.tailnet-xxxx.ts.net` 完全正常，
> 但容器裡的 Laravel 報 `php_network_getaddresses: getaddrinfo failed`。
> 很容易被誤判成「Tailscale 沒連上」而往錯的方向查。

### 1.3 ACL（只開兩個 port，不要全開）

在 Tailscale admin console → Access Controls，貼上：

```jsonc
{
  "tagOwners": {
    "tag:aivideo-prod": ["autogroup:admin"],
    "tag:aivideo-mac":  ["autogroup:admin"]
  },
  "acls": [
    // Mac → 生產機：只開 MySQL(3308) 與 Redis(6380)。
    // ⚠️ 刻意不給 "*"：Mac 只需要這兩個 port。把整台機器開給一台筆電，
    //    等於筆電被入侵就直接拿到 SSH。
    {
      "action": "accept",
      "src":    ["tag:aivideo-mac"],
      "dst":    ["tag:aivideo-prod:3308", "tag:aivideo-prod:6380"]
    },
    // 管理用：Paul 自己的身分可以 SSH 兩台
    {
      "action": "accept",
      "src":    ["autogroup:member"],
      "dst":    ["tag:aivideo-prod:22", "tag:aivideo-mac:22"]
    }
  ],
  // ⚠️ 不要開 Mac → 生產機以外的方向。生產機沒有任何理由主動連 Mac：
  //    所有工作都是 Mac 從 Redis「拉」的，不是生產機「推」的。
  "ssh": [
    {
      "action": "accept",
      "src":    ["autogroup:member"],
      "dst":    ["tag:aivideo-prod"],
      "users":  ["autogroup:nonroot"]
    }
  ]
}
```

### 1.4 驗證：必須是 direct，不能是 relay

```bash
# 在 Mac 上
tailscale ping <生產機 hostname>
```

期望輸出：

```
pong from vps (100.64.0.1) via 1.2.3.4:41641 in 18ms
```

> ⚠️ 若看到 `via DERP(tok)` 就是走中繼伺服器。DERP 能通但延遲與頻寬都差一個量級，
> 而 Mac 要把抓到的商品圖往 S3 送、每次任務要跟 MySQL 來回幾十次 query ——
> 走 relay 會讓單次抓取從 30 秒變成好幾分鐘，然後撞上 worker 的 600 秒 timeout。
>
> 多半是 NAT 打洞失敗。處理方式：
> - 確認 Mac 的路由器沒有開「對稱式 NAT」/ CGNAT
> - 在生產機上 `sudo tailscale up --advertise-exit-node=false` 並確認 UDP 41641 沒被擋
> - 最後手段：在生產機設定固定的 `--port` 並在防火牆開那個 UDP port

---

## 2. 生產機：MySQL / Redis 只綁 Tailscale 介面（⚠️ 最重要的一步）

目前 `docker-compose.yml` 是：

```yaml
  mysql:
    ports:
      - "3308:3306"     # ⚠️ 等同 0.0.0.0:3308 —— 綁在「所有」介面上，含公網
  redis:
    ports:
      - "6380:6379"     # ⚠️ 同上
```

Docker 的 `"3308:3306"` 短語法預設綁 `0.0.0.0`，**而且 Docker 會自己改 iptables
的 DOCKER 鏈，繞過 ufw / firewalld 的規則**。也就是說「我有開 ufw」不構成保護。
無密碼的 Redis 暴露在公網上，攻擊者可以直接塞序列化 payload 進 queue
→ 生產機上任意執行程式碼。這不是理論風險，是幾分鐘內就會發生的事。

**在生產機上把 ports 改成明確綁 Tailscale IP：**

```yaml
  mysql:
    ports:
      # ⚠️ 換成 `tailscale ip -4` 的實際結果。寫死 IP 是刻意的：
      #    Docker 不支援綁「介面名稱」，只能綁 IP。
      - "100.64.0.1:3308:3306"
  redis:
    ports:
      - "100.64.0.1:6380:6379"
    # ⚠️ requirepass 是第二道，不是備案。tailnet 裡任何一台被入侵的裝置
    #    （手機、另一台筆電）都能直連 Redis。
    command: ["redis-server", "--requirepass", "${REDIS_PASSWORD}", "--appendonly", "yes"]
```

> 💡 `phpmyadmin`（8083）與 `vite`（5174）在生產機上不該啟動。
> 如果 compose 檔是共用的，用 `docker compose up -d app queue web mysql redis`
> 明確列出要跑的服務。

### 2.1 Redis 密碼

```bash
openssl rand -base64 32          # 產一組
```

寫進兩台的 `.env.docker`：`REDIS_PASSWORD=...`
（生產機與 Mac 必須相同 —— 是同一個 Redis。）

### 2.2 MySQL 使用者只允許從 tailnet 連

```sql
-- 在生產機的 MySQL 裡
CREATE USER 'aivideo'@'100.%' IDENTIFIED BY '<密碼>';
GRANT ALL PRIVILEGES ON aivideo.* TO 'aivideo'@'100.%';
-- ⚠️ 不要留 'aivideo'@'%'，那等於任何來源都可以嘗試登入
DROP USER IF EXISTS 'aivideo'@'%';
FLUSH PRIVILEGES;
```

### 2.3 驗證：從公網必須連不上

```bash
# 在一台「不在 tailnet 裡」的機器上（手機熱點、另一台 VPS 都可以）
nc -vz <生產機公網IP> 3308      # 必須 timeout 或 refused
nc -vz <生產機公網IP> 6380      # 必須 timeout 或 refused
```

> ⚠️ 這兩條**必須失敗**。任何一條成功就立刻停掉容器回去修 ports 綁定。
> 從 tailnet 內測是測不出問題的 —— 要從外面測。

```bash
# 在 Mac 上（tailnet 內）必須成功
nc -vz 100.64.0.1 3308
nc -vz 100.64.0.1 6380
```

---

## 3. Mac 端設定

### 3.1 環境變數

```bash
cd ~/Desktop/aivideo
cp deploy/.env.mac.example .env.docker
$EDITOR .env.docker      # 填 Tailscale IP、密碼、APP_KEY（與生產機相同）
```

必填且最容易錯的幾項，見 `deploy/.env.mac.example` 的表格。重點：

- `APP_ROLE=mac` — 排程靠它決定只跑心跳
- `HORIZON_NAME=mac` / `HORIZON_ENV=production-mac`
- `APP_USE_REAL_APIS=true` — **false 的話 Mac 會回傳假資料且不報錯**
- `APP_KEY` 與生產機完全相同
- `DB_HOST` / `REDIS_HOST` 填 **IP**

### 3.2 啟動（⚠️ 必須帶 `--profile browser`）

```bash
docker compose --profile browser up -d browser queue-browser

# 確認服務清單
docker compose config --services                    # 預設不含 browser
docker compose --profile browser config --services  # 含 browser / queue-browser
```

> `browser` 與 `queue-browser` 刻意掛了 `profiles: ["browser"]`，
> 所以**生產機的 `docker compose up -d` 不會誤啟動它們**。

### 3.3 蝦皮登入（一次性，必須人工）

```bash
# 開一個有頭的 Chromium 手動登入，session 寫進 named volume
docker compose exec browser node scripts/login.js   # 腳本名以 browser/ 內實際檔案為準
```

> ⚠️ **絕對不要自動化登入流程。** 蝦皮的登入會觸發 SMS OTP 與人機驗證，
> 自動化嘗試是最高風險的行為。profile 只需要建立一次，之後靠
> `shopee_session_check` 監控是否失效。
>
> ⚠️ `browser_profiles` 必須保持 named volume，**不可改成 bind mount macOS 檔案系統**。
> Chrome 的 `user-data-dir` 是大量小檔 + SQLite + `flock()`，gRPC-FUSE / VirtioFS
> 對這組合支援很差 → profile 損壞 → 每次啟動都要重新 SMS OTP。

### 3.4 電源管理（⚠️ 一次性，Paul 手動執行）

launchd 的根本限制：**它只能在 Mac 醒著的時候工作**。睡眠中 launchd 自己也停了，
`StartInterval` 只會在醒來後補跑一次。要讓這台機器真的 24 小時能消費佇列，
必須用 `pmset`：

```bash
# 插電時永不睡眠（硬碟也不睡）
sudo pmset -c sleep 0 disksleep 0 displaysleep 10

# 每天 08:50 自動喚醒或開機 —— 給 09:00 的執行時段留 10 分鐘暖機
sudo pmset repeat wakeorpoweron MTWRFSU 08:50:00

# 確認
pmset -g sched
pmset -g custom
```

> ⚠️ 筆電**闔蓋仍會睡**，`pmset -c sleep 0` 管不到。要闔蓋繼續跑必須是
> clamshell 模式：接電源 + 接外接螢幕（或 HDMI dummy plug）+ 接鍵鼠。
> 不接外接螢幕就闔蓋 = 一定睡著，不管 pmset 怎麼設。
>
> ⚠️ 不要用第三方「防睡眠」App（Amphetamine / Caffeinate 常駐）取代 pmset：
> 它們持有 IOKit assertion，macOS 更新後常常靜默失效，而失效的症狀就是
> 「某天開始 browser queue 都沒跑」。

### 3.5 launchd（一次性）

```bash
# ⚠️ 先改 plist 裡的專案路徑（目前硬編 /Users/paul/Desktop/aivideo）
$EDITOR deploy/com.aivideo.browser.plist

cp deploy/com.aivideo.browser.plist ~/Library/LaunchAgents/
launchctl load -w ~/Library/LaunchAgents/com.aivideo.browser.plist

# 確認
launchctl list | grep aivideo
tail -f /tmp/aivideo-browser.log
```

移除：

```bash
launchctl unload -w ~/Library/LaunchAgents/com.aivideo.browser.plist
```

### 3.6 心跳排程

心跳由 Laravel 排程負責（`browser:heartbeat` 每分鐘），所以 Mac 上需要有東西跑
`schedule:run`。`deploy/mac-browser-up.sh` 只負責啟動容器與補一次心跳，**不是**
排程器。選一種：

```bash
# 選項 A：在 Mac 的 crontab（最簡單）
* * * * * cd /Users/paul/Desktop/aivideo && /usr/local/bin/docker compose exec -T queue-browser php artisan schedule:run >> /tmp/aivideo-schedule.log 2>&1

# 選項 B：在 queue-browser 容器裡跑 schedule:work（常駐，不需要 cron）
docker compose exec -d queue-browser php artisan schedule:work
```

> ⚠️ **生產機也要有一份 `schedule:run`**（跑 `browser:reap-stale`）。
> 兩邊都跑是刻意的，`config('app.role')` 會讓各自只執行該跑的那幾個。

---

## 4. Horizon 的 queue 分流

| | 改之前 | 改之後 |
|---|---|---|
| `config/horizon.php` 的 supervisor | 只有 `supervisor-1`，`queue => ['default']` | 多了 `supervisor-browser`，`queue => ['browser']` |
| `->onQueue('browser')` 的 job | **推進 Redis 後永遠沒人取**（Horizon 不監聽 browser queue） | 由 `supervisor-browser` 取用，`maxProcesses=1` 序列化執行 |
| 症狀 | 商品永遠卡在 `importing`，Horizon 上看不到 failed，operator 只能重按重試 | 正常執行；Mac 離線時留在佇列，開機後自動繼續 |

### 兩種部署法

**A. Mac 用 `queue:work`（目前 `docker-compose.yml` 的做法）**
`queue-browser` 服務跑 `queue:work redis --queue=browser --timeout=600`。
生產機跑 Horizon 或 `queue:work --queue=default`。
這種做法下 `supervisor-browser` 不會被用到，但 config 留著是對的 ——
它記錄了正確的參數，而且防止有人以為 `browser` queue 沒人要。

**B. 兩台都跑 Horizon（推薦，`/horizon` 上看得到完整狀態）**
```bash
# 生產機：HORIZON_ENV 留空 → 吃 production → 只部署 supervisor-1
php artisan horizon

# Mac：HORIZON_ENV=production-mac → 只部署 supervisor-browser
php artisan horizon
```

> ⚠️ Horizon 的 `environments` 是**覆寫**而非白名單：`ProvisioningPlan` 對每個
> environment 做 `array_replace_recursive(defaults, plan)`，所以 `defaults` 裡的
> supervisor 會出現在**每個** environment。要讓某台機器不部署某個 supervisor，
> 唯一的方法是把 `maxProcesses` 設成 `0`（`deploy()` 只在 `maxProcesses > 0` 時 add）。
> `config/horizon.php` 就是這樣做的。

### ⚠️ timeout 與 retry_after 的硬規則

```
worker timeout  <  queue retry_after
     600        <       900          ✅
```

`retry_after` 是「job 被取走後多久沒 ack 就重新派送」。若 `timeout >= retry_after`，
Redis queue 會在 worker 還在跑的時候把**同一個 job 再派一次** ——
對 `browser` queue 來說等於「同一個 Chrome `user-data-dir` 被兩個 process 同時開」，
profile 直接損壞，修復代價是重新 SMS OTP 登入蝦皮。

`tests/Unit/Config/HorizonQueueConfigTest.php` 會斷言這個關係。別把它改大。

---

## 5. 健康監控

生產機的 `/admin` 首頁有兩個 widget：

**`BrowserHealthWidget`**（30 秒輪詢）
- **Mac worker**：🟢 正常（最後心跳 X 分鐘前）/ 🔴 離線
  離線時明確寫「瀏覽器任務會留在佇列，Mac 開機後自動繼續」—— 這句話是為了
  阻止 operator 去重按重試按鈕把 rate limit 用光
- **browser 佇列積壓**：數量 + 最舊等待時間，超過 30 分鐘變紅；
  不在 09:00–23:00 時段時會寫「已刻意暫緩」（那是設計，不是故障）
- **本小時蝦皮抓取用量**：已用 / 上限
- **卡住的任務**：`needs_manual` 數量，點擊導向已套好 filter 的瀏覽器任務列表

**`CostSummaryWidget`**（120 秒輪詢）
本月總成本 / LLM 寫稿成本 / 其他成本 / 產出支數與平均每支成本。

> ⚠️ `products.cost_usd` 與 `products.llm_cost_usd` 是**互斥**的
> （`Product::addLlmCost()` 刻意不累加進 `cost_usd`）。總成本必須自己相加，
> 只看其中一欄會系統性低估 —— 而 LLM 寫稿常常是單支影片裡最貴的一項。

### 卡死任務的回收

`browser:reap-stale`（生產機，每 10 分鐘）把 `status=running` 且 `started_at`
超過 30 分鐘的 `browser_tasks` 標成 `needs_manual`，並把對應的 Product
轉成 `NeedsManual` + 寫 `needs_manual_reason`。

為什麼需要：Mac 睡眠或 Chromium 被 OOM kill 時，PHP process 直接消失，
`markRunning()` 寫下的 `running` 永遠不會被改掉 —— UI 上永遠顯示「執行中」，
operator 看不出來要介入。

```bash
docker compose exec -T app php artisan browser:reap-stale --minutes=30
```

> 若 Product 當下的狀態不允許轉 `needs_manual`（例如已 `published` / `archived`），
> 指令只寫 `needs_manual_reason` 不改狀態 —— `ProductStatus::TRANSITIONS`
> 不是裝飾品，繞過它會讓已完成的商品被一個過期的抓取任務拉回來。

---

## 6. 驗證清單

一次性設定完成後，照順序跑：

```bash
# --- Tailscale ---
tailscale status                          # 兩台都 online
tailscale ping <vps>                      # ⚠️ 必須顯示 direct，不能是 DERP relay

# --- 公網隔離（從 tailnet 外的機器跑）---
nc -vz <生產機公網IP> 3308                 # ⚠️ 必須失敗
nc -vz <生產機公網IP> 6380                 # ⚠️ 必須失敗

# --- Mac 端 ---
docker compose --profile browser config --services   # 含 browser / queue-browser
docker compose exec -T queue-browser php artisan tinker --execute="dump(DB::connection()->getPdo() !== null);"
docker compose exec -T queue-browser php artisan browser:heartbeat
docker compose exec -T queue-browser php artisan schedule:list   # 只該有 browser:heartbeat

# --- 生產機 ---
docker compose exec -T app php artisan schedule:list             # 只該有 browser:reap-stale
docker compose exec -T app php artisan browser:reap-stale --help
docker compose exec -T app php artisan tinker --execute="dump(app(App\Services\Browser\BrowserHealth::class)->workerAlive());"
# → 上一行在 Mac 的心跳寫入後 2 分鐘內必須是 true

# --- /admin ---
# 開 https://<domain>/admin → 首頁應看到「瀏覽器任務健康狀態」與「本月成本」
```

端到端：在 `/admin` 貼一個蝦皮商品連結 → Mac 的 `docker compose logs -f queue-browser`
應該看到任務被取走 → 商品狀態從 `importing` 變成 `product_pending_review`。

---

## 7. 常見故障對照表

| 症狀 | 最可能的原因 | 怎麼確認 |
|---|---|---|
| Widget 顯示「🔴 離線」但 Mac 開著 | Mac 的 `CACHE_STORE` 不是生產機的 Redis | Mac 上 `php artisan tinker` → `Cache::get('browser:worker:heartbeat')`，再到生產機看同一個 key |
| 任務一直 pending，worker 顯示正常 | 不在 09:00–23:00 執行時段 | Widget 的積壓卡會寫「已刻意暫緩」 |
| 任務一直 pending，不在時段也不是 | Mac 的 `HORIZON_ENV` 沒設（吃到 production → `supervisor-browser` maxProcesses=0） | Mac 上 `php artisan horizon:status`、`/horizon` 看 supervisor 清單 |
| 容器內 DB 連不上但 terminal 能 ping | `DB_HOST` 填了 MagicDNS 名稱 | 改成 `tailscale ip -4 <vps>` 的 IP |
| 抓取很慢、常常 timeout | Tailscale 走 DERP relay | `tailscale ping <vps>` 看是否 direct |
| 抓到的資料都是假的、固定內容 | Mac 的 `APP_USE_REAL_APIS=false`（拿到 StubBrowserAutomation） | 改成 true 後重啟 `queue-browser` |
| 每次啟動都要重新 SMS OTP | `browser_profiles` 被改成 bind mount | 改回 named volume |
| 同一商品被抓兩次、profile 損壞 | 某個 worker 的 `timeout >= retry_after(900)` | 跑 `tests/Unit/Config/HorizonQueueConfigTest.php` |
| 商品永遠「執行中」 | Mac 在執行途中睡著／crash | 等 `browser:reap-stale`（10 分鐘內）或手動跑 |

---

## 8. 一次性 vs 日常

**一次性（設定完就不用再碰）**
1. 兩台安裝 Tailscale + 設 ACL
2. 生產機把 MySQL / Redis 的 ports 綁到 Tailscale IP + 設 Redis `requirepass`
3. 生產機建 `'aivideo'@'100.%'` 並移除 `'aivideo'@'%'`
4. Mac 建 `.env.docker`（從 `deploy/.env.mac.example` 複製）
5. Mac 手動登入蝦皮建立 browser profile
6. Mac 跑 `pmset`（永不睡眠 + 每日 08:50 喚醒）
7. Mac 安裝 launchd agent
8. 兩台設定 `schedule:run`

**日常（自動，不用人管）**
- launchd 每 5 分鐘確保 browser 容器在跑
- `browser:heartbeat` 每分鐘（Mac）
- `browser:reap-stale` 每 10 分鐘（生產機）

**偶爾要看一眼**
- `/admin` 的 BrowserHealthWidget：worker 綠燈、積壓不紅、卡住數為 0
- `/admin` 的 CostSummaryWidget：平均每支成本有沒有異常上升
- 蝦皮 profile 失效時（`shopee_session_check` 失敗）要人工重新登入
