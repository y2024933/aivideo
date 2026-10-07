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
use Throwable;

/**
 * P7：素材核准後送 Remotion Lambda 渲染，送出後交給 PollRemotionRenderJob 輪詢。
 *
 * renderBlockers() 不為空時照樣走 Rendering → RenderFailed 並把原因寫進 status_message：
 * 自動放行觸發的渲染沒有人在畫面前，原因必須留在 UI 看得到的地方。
 */
final class SubmitRenderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const ENTRY_STATUSES = [ProductStatus::AssetsApproved, ProductStatus::RenderFailed];

    public int $tries = 1;

    public function __construct(public readonly string $productId) {}

    public function handle(VideoEditorContract $editor): void
    {
        $product = Product::find($this->productId);

        if (! $product || ! in_array($product->status, self::ENTRY_STATUSES, true)) {
            return;
        }

        $blockers = $product->renderBlockers();
        $product->transitionTo(ProductStatus::Rendering, 'system');

        if ($blockers !== []) {
            $this->markFailed($product, '無法渲染：'.implode('；', $blockers));

            return;
        }

        try {
            $renderId = $editor->submitRender($product)['render_id'];
        } catch (Throwable $e) {
            Log::error('[SubmitRenderJob] 送出渲染失敗', ['product_id' => $product->id, 'exception' => $e]);
            $this->markFailed($product, '送出渲染失敗：'.$e->getMessage());

            return;
        }

        $product->update(['render_id' => $renderId, 'status_message' => null]);
        PollRemotionRenderJob::dispatch($product->id, $renderId)->delay(now()->addSeconds(15));
    }

    private function markFailed(Product $product, string $message): void
    {
        $message = mb_substr($message, 0, 2000);
        $product->update(['status_message' => $message]);
        $product->transitionTo(ProductStatus::RenderFailed, 'system', $message);
    }
}
