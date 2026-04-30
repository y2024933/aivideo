<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class VideoDownloader
{
    public static function download(string $remoteUrl, string $directory = 'videos'): string
    {
        $response = Http::timeout(120)->get($remoteUrl);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to download video: HTTP {$response->status()}");
        }

        $filename = Str::uuid()->toString() . '.mp4';
        $path = "{$directory}/{$filename}";

        Storage::disk('public')->put($path, $response->body());

        return "/storage/{$path}";
    }
}
