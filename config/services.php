<?php

return [

    // API 模式切換：false = stub（開發），true = 真實 API
    'use_real_apis' => env('APP_USE_REAL_APIS', false),
    'api_budget_usd' => env('APP_API_BUDGET_USD', 10),

    // fal.ai (Flux Kontext 圖片生成)
    'fal' => [
        'key' => env('FAL_API_KEY'),
        'cost_per_image' => (float) env('FAL_COST_PER_IMAGE', 0.05),
        'poll_interval' => (int) env('FAL_POLL_INTERVAL', 3),
    ],

    // Kling AI (影片生成)
    'kling' => [
        'access_key' => env('KLING_ACCESS_KEY'),
        'secret_key' => env('KLING_SECRET_KEY'),
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
    ],

    // AWS (用於 Remotion Lambda + S3)
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

];
