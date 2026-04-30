<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BuildingCase;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

final class AssetDownloadController extends Controller
{
    public function download(BuildingCase $buildingCase): BinaryFileResponse
    {
        $shots = $buildingCase->shots()->orderBy('shot_order')->get();

        $dir = storage_path('app/private');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $zipPath = "{$dir}/{$buildingCase->id}_assets.zip";
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, '無法建立 ZIP 檔案');
        }

        foreach ($shots as $shot) {
            $order = str_pad((string) $shot->shot_order, 2, '0', STR_PAD_LEFT);
            $prefix = "{$order}_{$shot->id}";

            // 場景圖
            if ($shot->image_status === 'done' && $shot->image_url) {
                $localPath = $this->resolveStoragePath($shot->image_url);
                if ($localPath && file_exists($localPath)) {
                    $ext = pathinfo($localPath, PATHINFO_EXTENSION) ?: 'jpg';
                    $zip->addFile($localPath, "scenes/{$prefix}.{$ext}");
                }
            }

            // 影片
            if ($shot->video_status === 'done' && $shot->video_url) {
                $localPath = $this->resolveStoragePath($shot->video_url);
                if ($localPath && file_exists($localPath)) {
                    $ext = pathinfo($localPath, PATHINFO_EXTENSION) ?: 'mp4';
                    $zip->addFile($localPath, "videos/{$prefix}.{$ext}");
                }
            }

            // 配音
            if ($shot->voiceover_status === 'done' && $shot->voiceover_url) {
                $localPath = $this->resolveStoragePath($shot->voiceover_url);
                if ($localPath && file_exists($localPath)) {
                    $ext = pathinfo($localPath, PATHINFO_EXTENSION) ?: 'mp3';
                    $zip->addFile($localPath, "voiceovers/{$prefix}.{$ext}");
                }
            }
        }

        // 生成 SRT 字幕檔
        $srtContent = $this->generateSrt($shots);
        if ($srtContent) {
            $zip->addFromString('subtitles.srt', $srtContent);
        }

        $zip->close();

        $downloadName = "{$buildingCase->name}_素材包.zip";

        return response()->download($zipPath, $downloadName)->deleteFileAfterSend();
    }

    /**
     * 將 /storage/xxx 路徑轉換為本地絕對路徑
     */
    private function resolveStoragePath(string $url): ?string
    {
        // 處理 /storage/xxx 格式
        if (str_starts_with($url, '/storage/')) {
            $relativePath = substr($url, strlen('/storage/'));
            return storage_path("app/public/{$relativePath}");
        }

        // 處理完整 URL 中的 /storage/ 路徑
        $parsed = parse_url($url, PHP_URL_PATH);
        if ($parsed && str_starts_with($parsed, '/storage/')) {
            $relativePath = substr($parsed, strlen('/storage/'));
            return storage_path("app/public/{$relativePath}");
        }

        return null;
    }

    /**
     * 從 shots 生成 SRT 字幕內容
     */
    private function generateSrt($shots): ?string
    {
        $subtitleShots = $shots->filter(fn ($s) => filled($s->subtitle));
        if ($subtitleShots->isEmpty()) {
            return null;
        }

        $srt = '';
        $index = 1;
        $offset = 0.0;

        foreach ($shots as $shot) {
            $duration = (float) ($shot->duration_seconds ?: 5);

            if (blank($shot->subtitle)) {
                $offset += $duration;
                continue;
            }
            $start = $offset;
            $end = $offset + $duration;

            $srt .= $index . "\n";
            $srt .= $this->formatSrtTime($start) . ' --> ' . $this->formatSrtTime($end) . "\n";
            $srt .= $shot->subtitle . "\n\n";

            $offset = $end;
            $index++;
        }

        return $srt;
    }

    /**
     * 秒數轉 SRT 時間格式 (HH:MM:SS,mmm)
     */
    private function formatSrtTime(float $seconds): string
    {
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);
        $secs = (int) floor($seconds % 60);
        $millis = (int) round(($seconds - floor($seconds)) * 1000);

        return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $secs, $millis);
    }
}
