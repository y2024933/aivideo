<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageDownloader;
use App\Services\Pipeline;
use App\Services\Shopee\ShopeeLinkParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 下載商品圖並雙寫 public disk + S3。
 *
 * 刻意跑在 default queue 而不是 browser queue：這是純 HTTP GET，不需要 Chromium，
 * 擠在併發 1 的 browser queue 只會讓下一個商品等上幾分鐘。
 *
 * 圖片下載走 ImageDownloader::shopee()：它會自動帶 Referer（CDN 少了會回 403）
 * 並去掉 @resize_wNNN 後綴拿原圖（縮圖拿去做 1080x1920 影片會糊掉）。
 */
final class DownloadProductImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    /** 最多下載幾張。30 秒影片用不到 12 張，多抓只是浪費頻寬與 S3 空間。 */
    private const MAX_IMAGES = 12;

    /** @param list<string> $imageUrls 留空則改抓 product_images 裡 status=pending 的 source_url */
    public function __construct(public readonly string $productId, public readonly array $imageUrls = [])
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $product = Product::find($this->productId);

        if (! $product) {
            return;
        }

        $urls = $this->imageUrls !== []
            ? $this->imageUrls
            : $product->images()->where('status', 'pending')->pluck('source_url')->filter()->all();

        foreach (array_slice(array_values(array_unique($urls)), 0, self::MAX_IMAGES) as $index => $url) {
            $this->store($product, (string) $url, $index);
        }

        // 圖片是 checkpoint ① 的 gate（remote_url 缺一張就不給核准），所以放行判斷
        // 必須等到這裡才做，不能在抓完商品資料時就做。
        app(Pipeline::class)->afterProductSubmitted($product->refresh());
    }

    private function store(Product $product, string $url, int $index): void
    {
        $hash = $this->hashOf($url);

        /** @var ProductImage $image */
        $image = $product->images()->firstOrNew(['image_hash' => $hash]);

        try {
            $file = ImageDownloader::shopee($url);

            $image->fill([
                'sort_order' => $image->exists ? $image->sort_order : $index,
                'source' => 'shopee',
                'source_url' => $url,
                'local_path' => $file->localPath,
                'remote_url' => $file->remoteUrl,
                'width' => $file->width,
                'height' => $file->height,
                'bytes' => $file->bytes,
                'mime' => $file->mime,
                'is_primary' => $index === 0,
                'is_selected' => true,
                'status' => 'done',
                'error_message' => null,
                // 蝦皮商品圖的著作權在賣家，未取得授權前一律 unverified
                'license_status' => $image->license_status ?: 'unverified',
            ])->save();
        } catch (Throwable $e) {
            Log::warning('[DownloadProductImagesJob] 圖片下載失敗', ['product_id' => $product->id, 'url' => $url, 'error' => $e->getMessage()]);

            $image->fill([
                'sort_order' => $image->exists ? $image->sort_order : $index,
                'source' => 'shopee',
                'source_url' => $url,
                'status' => 'failed',
                'is_selected' => false,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ])->save();
        }
    }

    /** 蝦皮圖片的 hash 就是 URL 最後一段（去掉 resize 後綴），用它做 unique 去重 */
    private function hashOf(string $url): string
    {
        $basename = basename((string) parse_url(ShopeeLinkParser::originalImageUrl($url), PHP_URL_PATH));

        return $basename !== '' ? mb_substr($basename, 0, 128) : mb_substr(sha1($url), 0, 128);
    }

    public function failed(Throwable $e): void
    {
        Product::whereKey($this->productId)
            ->where('status', ProductStatus::ProductPendingReview)
            ->update(['status_message' => '商品圖下載失敗：' . mb_substr($e->getMessage(), 0, 500)]);
    }
}
