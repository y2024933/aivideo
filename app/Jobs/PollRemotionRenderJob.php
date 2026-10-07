<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\Contracts\VideoEditorContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class PollRemotionRenderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $productId,
        public readonly string $renderId,
        public readonly int $pollCount = 0,
    ) {}

    public function handle(VideoEditorContract $videoEditor): void
    {
        $product = Product::find($this->productId);

        if (! $product || $product->final_video_url) {
            return; // 已完成或已刪除
        }

        // 超過 20 次輪詢視為逾時（20 x 15s = 5 分鐘）
        if ($this->pollCount >= 20) {
            Log::error('[PollRemotionRenderJob::handle] Polling timeout', ['product_id' => $this->productId]);
            $this->markRenderFailed($product, '影片渲染逾時');

            return;
        }

        try {
            $result = $videoEditor->queryRenderStatus($this->renderId);
        } catch (\Throwable $e) {
            Log::error('[PollRemotionRenderJob::handle] 查詢失敗', ['exception' => $e]);
            self::dispatch($this->productId, $this->renderId, $this->pollCount + 1)->delay(now()->addSeconds(15));

            return;
        }

        match ($result['status']) {
            'completed' => $this->handleCompleted($product, $result),
            'failed' => $this->handleFailed($product, $result),
            default => self::dispatch($this->productId, $this->renderId, $this->pollCount + 1)->delay(now()->addSeconds(15)),
        };
    }

    private function handleCompleted(Product $product, array $result): void
    {
        $product->update([
            'final_video_url' => $result['video_url'],
            'final_video_remote_url' => $result['video_url'],
            'render_id' => null,
            'status_message' => null,
        ]);
        $product->transitionTo(ProductStatus::FinalPendingReview, 'system');
    }

    private function handleFailed(Product $product, array $result): void
    {
        Log::error('[PollRemotionRenderJob::handleFailed] 渲染失敗', [
            'product_id' => $product->id,
            'error' => $result['error'],
        ]);
        $this->markRenderFailed($product, $result['error'] ?? '影片渲染失敗');
    }

    /**
     * 清掉 render_id、把錯誤寫進 status_message 讓 UI 看得到，並轉為 RenderFailed
     */
    private function markRenderFailed(Product $product, string $message): void
    {
        $product->update(['render_id' => null, 'status_message' => $message]);
        $product->transitionTo(ProductStatus::RenderFailed, 'system', $message);
    }
}
