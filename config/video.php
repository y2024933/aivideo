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

];
