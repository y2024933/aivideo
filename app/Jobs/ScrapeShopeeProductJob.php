<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Data\Browser\BrowserResult;
use App\Data\ShopeeItemRef;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\Browser\BrowserRateLimiter;
use App\Services\Contracts\BrowserAutomationContract;
use App\Services\Shopee\ShopeeProductMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * P5：用瀏覽器抓蝦皮商品資料。
 *
 * ⚠️ tries 必須是 1。重試與 backoff 全部在 PlaywrightBrowserAutomation 內部做
 *    （10／30／90 秒）；queue 再重試 3 次就變成 16 次請求打同一個商品，
 *    rate limit 會被自己的重試吃光，而且那正是最容易被風控的行為模式。
 *
 * ⚠️ 走 browser queue。該 queue 的 worker 只有一個、瀏覽器併發固定 1，
 *    是刻意的序列化 —— 並行抓取是機器人特徵。
 */
final class ScrapeShopeeProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /** 內部 backoff 最壞情況 10+30+90 = 130 秒，加上 4 次各 ~45 秒的瀏覽器操作 */
    public int $timeout = 900;

    private const RATE_LIMIT_ACTION = 'shopee_scrape';

    /** 可以進抓取的狀態（NeedsManual／ImportFailed 是人工處理後重跑） */
    private const ENTRY_STATUSES = [
        ProductStatus::Draft,
        ProductStatus::ImportFailed,
        ProductStatus::ProductPendingReview,
        ProductStatus::NeedsManual,
    ];

    /** @param bool $ignoreTimeWindow 只有 Filament 的人工重試會帶 true */
    public function __construct(public readonly string $productId, public readonly bool $ignoreTimeWindow = false)
    {
        $this->onQueue('browser');
    }

    public function handle(BrowserAutomationContract $browser, BrowserRateLimiter $limiter, ShopeeProductMapper $mapper): void
    {
        $product = Product::find($this->productId);

        if (! $product || ! in_array($product->status, self::ENTRY_STATUSES, true)) {
            return;
        }

        if (blank($product->shopee_shop_id) || blank($product->shopee_item_id)) {
            $this->fail($product, '這個商品沒有蝦皮 shop_id／item_id，請改用手動建立或重新貼連結。');

            return;
        }

        $product->transitionTo(ProductStatus::Importing, 'browser');

        // 時間窗與頻率都在呼叫前檢查，被擋就根本不開瀏覽器
        if ($reason = $limiter->reasonToBlock(self::RATE_LIMIT_ACTION, ['ignore_time_window' => $this->ignoreTimeWindow])) {
            $this->fail($product, $reason);

            return;
        }

        $ref = new ShopeeItemRef((int) $product->shopee_shop_id, (int) $product->shopee_item_id, (string) ($product->source_url ?: "https://shopee.tw/product/{$product->shopee_shop_id}/{$product->shopee_item_id}"));

        // 請求送出前就記帳：失敗的請求同樣打到了蝦皮
        $limiter->hit(self::RATE_LIMIT_ACTION);
        $result = $browser->scrapeShopeeProduct($ref, ['subject' => $product]);

        if ($result->needsManual()) {
            $product->update(['needs_manual_reason' => $this->manualReason($result)]);
            $product->transitionTo(ProductStatus::NeedsManual, 'browser');

            return;
        }

        if (! $result->usable()) {
            $this->fail($product, sprintf('抓取失敗（%s）：%s', $result->errorCode ?? 'unknown', $result->errorMessage ?? '無錯誤訊息'));

            return;
        }

        try {
            $data = $mapper->fromBrowserResult($result, $ref);
        } catch (Throwable $e) {
            Log::error('[ScrapeShopeeProductJob] 映射失敗', ['product_id' => $product->id, 'exception' => $e]);
            $this->fail($product, '抓到資料但無法解析：' . mb_substr($e->getMessage(), 0, 500));

            return;
        }

        $product->update([
            ...$data->toProductAttributes(),
            'source' => 'shopee_link',
            'source_url' => $ref->canonicalUrl,
            // 降級的資料來源不可靠，訊息要留在頁面上，operator 才知道要逐欄核對
            'status_message' => $result->isDegraded()
                ? '⚠️ 降級抓取（DOM 解析，未攔到官方 API）：價格、評分、規格可能缺漏或有誤，請人工核對後再核准。'
                : null,
            'needs_manual_reason' => null,
        ]);

        // 圖片走 default queue（純 HTTP 下載不需要瀏覽器），完成後才判斷能不能自動放行 ①
        DownloadProductImagesJob::dispatch($product->id, $data->images);

        $product->transitionTo(ProductStatus::ProductPendingReview, 'browser', $result->isDegraded() ? '降級抓取' : null);
    }

    /** importing → import_failed 並留下繁中原因 */
    private function fail(Product $product, string $message): void
    {
        $product->update(['status_message' => mb_substr($message, 0, 2000)]);

        $product->status === ProductStatus::Importing
            ? $product->transitionTo(ProductStatus::ImportFailed, 'browser')
            : $product->forceStatus(ProductStatus::ImportFailed, 'browser', $message);
    }

    private function manualReason(BrowserResult $result): string
    {
        return implode("\n", [
            '瀏覽器抓取需要人工介入：' . ($result->errorMessage ?? $result->errorCode ?? '未知原因'),
            '常見原因：觸發人機驗證、商品僅限 App 瀏覽、或蝦皮改版導致 API 攔不到。',
            '處理方式：到 Filament 的「瀏覽器任務」看失敗截圖，確認後可手動補齊商品資料，或稍後重試。',
        ]);
    }
}
