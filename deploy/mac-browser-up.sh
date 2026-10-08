#!/usr/bin/env bash
#
# Mac 端：確保 browser 容器與 browser queue worker 在跑。
#
# 由 deploy/com.aivideo.browser.plist 呼叫（RunAtLoad + StartInterval=300）。
# 設計成 idempotent —— 重複執行是正常路徑，不是例外。
#
# ⚠️ 這支腳本不會自己安裝任何東西、不改系統設定。Tailscale 與 pmset 要人工做一次，
#    見 deploy/README.md。
#
set -uo pipefail

cd "$(dirname "$0")/.." || exit 1

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

# ---------------------------------------------------------------------------
# 1. 等 Docker daemon。登入後 launchd 跑得比 Docker Desktop 快，直接下指令會失敗。
#    最多等 3 分鐘；還沒好就 exit 1，讓 launchd 的 KeepAlive 重試。
# ---------------------------------------------------------------------------
for _ in $(seq 1 36); do
    docker info >/dev/null 2>&1 && break
    sleep 5
done

if ! docker info >/dev/null 2>&1; then
    log "Docker daemon 未就緒，交給 launchd 重試"
    exit 1
fi

# ---------------------------------------------------------------------------
# 2. 檢查能不能連到生產機的 Redis / MySQL（走 Tailscale）。
#    連不到就不要啟動 worker：worker 會立刻 crash loop，而 log 裡只會看到
#    一堆 connection refused，比「根本沒啟動」更難診斷。
# ---------------------------------------------------------------------------
REDIS_HOST="$(grep -E '^REDIS_HOST=' .env.docker 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '"')"
REDIS_PORT="$(grep -E '^REDIS_PORT=' .env.docker 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '"')"
REDIS_PORT="${REDIS_PORT:-6379}"

if [ -n "${REDIS_HOST:-}" ] && [ "${REDIS_HOST}" != "redis" ]; then
    if ! nc -z -G 5 "${REDIS_HOST}" "${REDIS_PORT}" >/dev/null 2>&1; then
        log "連不到 Redis ${REDIS_HOST}:${REDIS_PORT}（Tailscale 可能還沒連上），稍後重試"
        exit 1
    fi
    log "Redis ${REDIS_HOST}:${REDIS_PORT} 可達"
fi

# ---------------------------------------------------------------------------
# 3. 拉起 browser profile 的服務。
#    ⚠️ 必須帶 --profile browser，否則 docker-compose.yml 裡的 browser /
#       queue-browser 會被跳過（它們刻意掛了 profiles）。
#    up -d 對已在跑的服務是 no-op。
# ---------------------------------------------------------------------------
log "啟動 browser / queue-browser"
docker compose --profile browser up -d browser queue-browser || {
    log "docker compose up 失敗"
    exit 1
}

# ---------------------------------------------------------------------------
# 4. 寫一次心跳（不要等排程的那一分鐘）。
#    排程本身由 app 容器的 schedule:run 負責，這裡只是讓「剛睡醒」的狀態
#    馬上反映到生產機的 /admin 上。
# ---------------------------------------------------------------------------
docker compose exec -T queue-browser php artisan browser:heartbeat >/dev/null 2>&1 \
    && log "心跳已寫入" \
    || log "心跳寫入失敗（容器可能還在 composer install）"

log "完成"
exit 0
