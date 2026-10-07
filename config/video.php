<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 影片輸出規格
|--------------------------------------------------------------------------
| fps 與 canvas 必須與 remotion/src/Root.jsx 的 Composition 設定一致，
| 不一致會導致 Lambda 算出的總幀數與後端估算的秒數對不上。
*/

return [

    'fps' => 30,

    'canvas' => [
        'width' => 1080,
        'height' => 1920,
    ],

    // 新商品的字幕預設值（寫入 products.subtitle_settings）
    'subtitle_defaults' => [
        'fontSize' => 'medium',
        'color' => '#ffffff',
        'position' => 'bottom',
        'animation' => 'slideIn',
        'fontFamily' => 'default',
        'textStroke' => 'none',
        'textShadow' => 'none',
        'bgStyle' => 'dark',
    ],

    /*
    | 自動放行（App\Services\Pipeline）：gate 全過就跳過該 checkpoint，任何一條不過照樣停下等人。
    | ② 腳本預設關閉 —— 違規的法律責任在 operator 身上，要自動放行請明確打開。
    */
    'autopilot' => [
        'product' => filter_var(env('AUTOPILOT_PRODUCT', true), FILTER_VALIDATE_BOOLEAN),
        'script' => filter_var(env('AUTOPILOT_SCRIPT', false), FILTER_VALIDATE_BOOLEAN),
        'assets' => filter_var(env('AUTOPILOT_ASSETS', true), FILTER_VALIDATE_BOOLEAN),
    ],

];
