<?php

return [

    // API 模式切換：false = stub（開發），true = 真實 API
    'use_real_apis' => filter_var(env('APP_USE_REAL_APIS', false), FILTER_VALIDATE_BOOLEAN),
    'api_budget_usd' => env('APP_API_BUDGET_USD', 10),

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

];
