<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ImageOptimizer
{
    /**
     * 壓縮圖片並產生 WebP 版本
     *
     * @return string|null WebP 檔案路徑，失敗回傳 null
     */
    public function optimize(string $path, int $quality = 80, string $disk = 'public'): ?string
    {
        try {
            $fullPath = Storage::disk($disk)->path($path);
            if (!file_exists($fullPath) || !@getimagesize($fullPath)) return null;

            $image = Image::read($fullPath);
            $image->save($fullPath, quality: $quality);

            // 產生 WebP
            $webpPath = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $path);
            $image->toWebp($quality)->save(Storage::disk($disk)->path($webpPath));

            return $webpPath;
        } catch (\Throwable $e) {
            Log::error('[ImageOptimizer::optimize] 圖片處理失敗', [
                'path' => $path,
                'exception' => $e,
            ]);
            return null;
        }
    }
}
