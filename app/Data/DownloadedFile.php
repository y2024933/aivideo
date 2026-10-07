<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * 下載／上傳完成的素材。
 *
 * localPath 是可對外存取的本地網址路徑（/storage/...），remoteUrl 是 S3 絕對 URL——
 * Remotion Lambda 只能吃 remoteUrl，localPath 僅供後台預覽。
 */
final class DownloadedFile extends Data
{
    public function __construct(
        public string $localPath,
        public ?string $remoteUrl,
        public ?int $width,
        public ?int $height,
        public int $bytes,
        public ?string $mime,
    ) {}

    /** public disk 上的相對路徑（去掉 /storage/ 前綴） */
    public function diskPath(): string
    {
        return ltrim(str_replace('/storage/', '', $this->localPath), '/');
    }
}
