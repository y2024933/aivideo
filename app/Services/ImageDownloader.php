<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\DownloadedFile;
use App\Services\Shopee\ShopeeLinkParser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 圖片下載／入庫。
 *
 * 本地與 S3 雙寫：Remotion Lambda 只能讀公開 URL，少了 S3 這一步
 * 之後 Ken Burns 一定在 Lambda 上 404。S3 失敗故意不吞（filesystems s3.throw = true），
 * 靜默產生 remote_url = null 遠比當下丟 exception 難 debug。
 */
final class ImageDownloader
{
    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public function download(string $url, string $directory = 'images', array $headers = []): DownloadedFile
    {
        $response = Http::withHeaders($headers)->timeout(30)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("圖片下載失敗：HTTP {$response->status()} {$url}");
        }

        return $this->store($response->body(), $directory, self::extensionFor($response->header('Content-Type'), $url));
    }

    /** 蝦皮專用：自動帶 Referer + 把 @resize_wNNN 後綴去掉拿原圖 */
    public static function shopee(string $url, string $directory = 'products'): DownloadedFile
    {
        return (new self)->download(
            ShopeeLinkParser::originalImageUrl($url),
            $directory,
            ['Referer' => config('services.shopee.referer'), 'User-Agent' => 'Mozilla/5.0'],
        );
    }

    /** 把已經在 public disk 的檔案（Filament FileUpload 產物）補齊 metadata 並同步到 S3 */
    public function adoptPublicFile(string $diskPath): DownloadedFile
    {
        if (! Storage::disk('public')->exists($diskPath)) {
            throw new RuntimeException("本地檔案不存在：{$diskPath}");
        }

        return $this->persist($diskPath, Storage::disk('public')->get($diskPath));
    }

    /** 寫入 public disk 並同步 S3，檔名用 uuid 避免原始檔名造成的衝突與編碼問題 */
    public function store(string $contents, string $directory = 'images', string $extension = 'jpg'): DownloadedFile
    {
        $diskPath = trim($directory, '/') . '/' . Str::uuid()->toString() . '.' . $extension;
        Storage::disk('public')->put($diskPath, $contents);

        return $this->persist($diskPath, $contents);
    }

    private function persist(string $diskPath, string $contents): DownloadedFile
    {
        // getimagesizefromstring 失敗（非圖片或格式不支援）時回 false，不中斷流程但 width/height 留 null
        $info = @getimagesizefromstring($contents) ?: null;

        Storage::disk('s3')->put($diskPath, $contents, 'public');

        return new DownloadedFile(
            localPath: '/storage/' . $diskPath,
            remoteUrl: Storage::disk('s3')->url($diskPath),
            width: isset($info[0]) ? (int) $info[0] : null,
            height: isset($info[1]) ? (int) $info[1] : null,
            bytes: strlen($contents),
            mime: $info['mime'] ?? null,
        );
    }

    private static function extensionFor(?string $contentType, string $url): string
    {
        if ($ext = self::EXTENSIONS[strtolower(trim(explode(';', (string) $contentType)[0]))] ?? null) {
            return $ext;
        }

        $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? $ext : 'jpg';
    }
}
