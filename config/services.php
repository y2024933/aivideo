<?php

return [

    // API 模式切換：false = stub（開發），true = 真實 API
    'use_real_apis' => filter_var(env('APP_USE_REAL_APIS', false), FILTER_VALIDATE_BOOLEAN),
    'api_budget_usd' => env('APP_API_BUDGET_USD', 10),

    /*
     * 動畫供應商的全域預設（App\Enums\VideoProvider）。
     *
     * 預設 none：純商品圖 + Ken Burns 不花錢，也沒有 AI 把商品畫變形的風險。
     * products.video_provider / shots.video_provider 可逐層覆寫（null = 繼承上一層），
     * 解析一律經 App\Services\Video\VideoGeneratorFactory。
     */
    'video_provider' => env('VIDEO_PROVIDER', 'none'),

    // Kling AI (影片生成)
    'kling' => [
        'access_key' => env('KLING_ACCESS_KEY'),
        'secret_key' => env('KLING_SECRET_KEY'),
        'model' => env('KLING_MODEL', 'kling-v2-5-turbo'),
        'mode' => env('KLING_MODE', 'std'),
        'duration' => (int) env('KLING_DURATION', 5),
        'cost_per_video' => (float) env('KLING_COST_PER_VIDEO', 0.21),
    ],

    // Azure TTS (配音)
    'azure_tts' => [
        'key' => env('AZURE_TTS_KEY'),
        'region' => env('AZURE_TTS_REGION', 'eastasia'),
    ],

    // Remotion Lambda (影片剪接)
    'remotion' => [
        'function_name' => env('REMOTION_FUNCTION_NAME'),
        'serve_url' => env('REMOTION_SERVE_URL'),
        'region' => env('REMOTION_REGION', 'us-east-1'),
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'version' => env('REMOTION_VERSION', '4.0.454'),
        'frames_per_lambda' => (int) env('REMOTION_FRAMES_PER_LAMBDA', 150),
    ],

    // LLM 寫稿供應商（App\Enums\ScriptProvider）。預設 Gemini：免費額度不綁卡。
    'script_provider' => env('SCRIPT_PROVIDER', 'gemini'),

    // Google Gemini（LLM 自動寫稿，Google AI Studio 免費 tier）
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        // -latest alias 會隨官方版本更迭自動換到最新的 Flash（破壞性變更有 2 週預告），
        // 比寫死 gemini-3.8-flash 這類 stable id 更不容易因為模型下架而壞掉
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        /*
         * 備援 model（逗號分隔，依序嘗試）。
         *
         * 免費 tier 的實測狀況（2026-10-07）：
         *   gemini-3.8-flash      → 503 high demand（熱門，常被擠爆）
         *   gemini-flash-latest   → 對本帳號直接 hang 到逾時，不要用 -latest alias
         *   gemini-2.5-flash(-lite) → 404「no longer available to new users」
         *   gemini-3.5-flash-lite / 3.1-flash-lite / 3-flash-preview → 正常
         */
        'fallback_models' => env('GEMINI_FALLBACK_MODELS', 'gemini-3.1-flash-lite,gemini-3-flash-preview,gemini-3.8-flash'),
        'connect_timeout' => (int) env('GEMINI_CONNECT_TIMEOUT', 15),
        'timeout' => (int) env('GEMINI_TIMEOUT', 180),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 8000),
        'temperature' => (float) env('GEMINI_TEMPERATURE', 0.9),
        // 免費 tier 為 0；升付費時填實際單價（USD / MTok）
        'cost_per_mtok_input' => (float) env('GEMINI_COST_IN', 0.0),
        'cost_per_mtok_output' => (float) env('GEMINI_COST_OUT', 0.0),
    ],

    // Anthropic Claude（LLM 自動寫稿，SCRIPT_PROVIDER=claude 時才會用到）
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 8000),
        'timeout' => (int) env('ANTHROPIC_TIMEOUT', 180),
        // 單價 USD / MTok，僅用於 products.llm_cost_usd 記帳
        'cost_per_mtok_input' => (float) env('ANTHROPIC_COST_IN', 5.0),
        'cost_per_mtok_output' => (float) env('ANTHROPIC_COST_OUT', 25.0),
        'cost_per_mtok_cache_write' => (float) env('ANTHROPIC_COST_CACHE_WRITE', 6.25),
        'cost_per_mtok_cache_read' => (float) env('ANTHROPIC_COST_CACHE_READ', 0.5),
    ],

    // 蝦皮（商品連結解析 / 圖片 CDN / 上架）
    'shopee' => [
        // 部分 CDN 節點會對缺少 Referer 的請求回 403
        'referer' => 'https://shopee.tw/',
        'image_cdn' => env('SHOPEE_IMAGE_CDN', 'https://down-tw.img.susercontent.com/file/'),
        'pdp_api' => 'https://shopee.tw/api/v4/pdp/get_pc',
        // get_pc 回傳的 price 單位是原價 × 100000
        'price_divisor' => 100000,
        'creator_url' => env('SHOPEE_CREATOR_URL', 'https://creator.shopee.tw/'),
    ],

    /*
     * 瀏覽器自動化服務（browser/ 目錄的 Fastify + rebrowser-playwright）。
     *
     * ⚠️ base_url 在生產機必須留空。瀏覽器服務只跑在開發機（docker compose --profile browser），
     *    留空時 PlaywrightBrowserAutomation 會立刻回 failed，誤派到生產機的 job 才不會
     *    卡在 queue 裡等 timeout。
     */
    'browser' => [
        'base_url' => env('BROWSER_SERVICE_URL'),
        'token' => env('BROWSER_SERVICE_TOKEN'),
        // 蝦皮商品頁從 goto 到攔到 get_pc 常要 20–40 秒，timeout 不能照一般 API 設 30
        'timeout' => (int) env('BROWSER_SERVICE_TIMEOUT', 120),
        'connect_timeout' => (int) env('BROWSER_SERVICE_CONNECT_TIMEOUT', 10),

        /*
         * 頻率上限（每小時）。這是帳號風險控管而非效能設定 ——
         * 蝦皮封的是帳號，而帳號只能人工 SMS OTP 恢復，寧可慢也不要被封。
         */
        'rate_limits' => [
            'shopee_scrape_per_hour' => (int) env('BROWSER_SHOPEE_SCRAPE_PER_HOUR', 20),
            'external_image_per_hour' => (int) env('BROWSER_EXTERNAL_IMAGE_PER_HOUR', 60),
            'session_check_per_hour' => (int) env('BROWSER_SESSION_CHECK_PER_HOUR', 12),
        ],

        /*
         * 執行時段（台灣時間）。半夜連續抓取是最明顯的機器人特徵之一。
         * jitter 是「動作之間」的隨機停頓秒數，交給 Node 端的 humanize.js 執行，
         * PHP 只負責把範圍傳過去（在 PHP sleep 會佔住 queue worker）。
         */
        'window' => [
            'start' => env('BROWSER_WINDOW_START', '09:00'),
            'end' => env('BROWSER_WINDOW_END', '23:00'),
            'timezone' => env('BROWSER_WINDOW_TIMEZONE', 'Asia/Taipei'),
            'jitter' => [
                'min' => (float) env('BROWSER_JITTER_MIN', 0.8),
                'max' => (float) env('BROWSER_JITTER_MAX', 3.0),
            ],
        ],

        /*
         * 重試 backoff（秒，逗號分隔）。預設 10／30／90 = 最多 1 次初試 + 3 次重試。
         * ⚠️ 重試只在這一層做，ScrapeShopeeProductJob 的 tries 必須是 1 ——
         *    雙重重試會讓單一商品打出 16 次請求，直接撞爆 rate limit。
         */
        'retry' => [
            'delays' => env('BROWSER_RETRY_DELAYS', '10,30,90'),
        ],

        // 失敗時的截圖／trace 存哪個 disk（Remotion 以外唯一要長期保存的除錯素材）
        'artifacts_disk' => env('BROWSER_ARTIFACTS_DISK', 's3'),

        /*
         * 混合部署時顯示在 Filament 心跳卡上的機器名稱（Mac 端設 BROWSER_WORKER_NAME）。
         *
         * ⚠️ 必須走 config 而不是在 command 裡直接 env()：`php artisan config:cache`
         *    之後 LoadEnvironmentVariables 會 early return 不載入 .env，env() 一律回 null。
         *    那會讓心跳卡上永遠顯示隨機的容器 hostname，而且只在生產環境才出錯。
         */
        'worker_name' => env('BROWSER_WORKER_NAME'),

        /*
         * 各服務可用的瀏覽器 profile 名稱（逗號分隔）。profile = 一組已登入的 session。
         *
         * ⚠️ dola 的風險警告請讀 App\Services\Video\DolaAccountPool 的 class docblock：
         *    Dola 只有 Google OAuth 登入、絕對不能自動重登（會鎖 Google 帳號），
         *    多帳號輪替刷免費額度是明確的 ToS 違規且有連鎖封號風險。
         *    留空（預設）= 不啟用 Dola。
         */
        'profiles' => [
            'dola' => env('BROWSER_PROFILES_DOLA', ''),
        ],
    ],

];
