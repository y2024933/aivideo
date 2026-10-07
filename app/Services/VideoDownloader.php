<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\DownloadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * B-roll 影片下載。與 ImageDownloader 同樣本地 + S3 雙寫，
 * Remotion Lambda 讀的是 S3 的絕對 URL。
 */
final class VideoDownloader
{
    public static function download(string $remoteUrl, string $directory = 'videos'): DownloadedFile
    {
        $response = Http::timeout(120)->get($remoteUrl);

        if (! $response->successful()) {
            throw new RuntimeException("影片下載失敗：HTTP {$response->status()}");
        }

        $contents = $response->body();
        $diskPath = trim($directory, '/') . '/' . Str::uuid()->toString() . '.mp4';

        Storage::disk('public')->put($diskPath, $contents);
        Storage::disk('s3')->put($diskPath, $contents, 'public');

        return new DownloadedFile(
            localPath: '/storage/' . $diskPath,
            remoteUrl: Storage::disk('s3')->url($diskPath),
            width: null,
            height: null,
            bytes: strlen($contents),
            mime: $response->header('Content-Type') ?: 'video/mp4',
        );
    }
}
