<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ImageDownloader
{
    /**
     * 下載遠端圖片到本地 storage，回傳本地 URL
     */
    public static function download(string $remoteUrl, string $directory = 'images'): string
    {
        $response = Http::timeout(30)->get($remoteUrl);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to download image: HTTP {$response->status()}");
        }

        $extension = self::guessExtension($response->header('Content-Type', ''), $remoteUrl);
        $filename = Str::uuid()->toString() . '.' . $extension;

        Storage::makeDirectory("public/{$directory}");
        Storage::put("public/{$directory}/{$filename}", $response->body());
        chmod(storage_path("app/public/{$directory}/{$filename}"), 0644);

        return "/storage/{$directory}/{$filename}";
    }

    private static function guessExtension(string $contentType, string $url): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (isset($map[$contentType])) {
            return $map[$contentType];
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext = pathinfo($path, PATHINFO_EXTENSION);

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) ? $ext : 'jpg';
    }
}
