<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ImageDownloader
{
    public static function download(string $remoteUrl, string $directory = 'images'): string
    {
        $response = Http::timeout(30)->get($remoteUrl);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to download image: HTTP {$response->status()}");
        }

        $extension = self::guessExtension($response->header('Content-Type', ''), $remoteUrl);
        $filename = Str::uuid()->toString() . '.' . $extension;
        $path = "{$directory}/{$filename}";

        // 用 public disk（有正確的 visibility 設定）
        Storage::disk('public')->put($path, $response->body());

        return "/storage/{$path}";
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

        $ext = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) ? $ext : 'jpg';
    }
}
