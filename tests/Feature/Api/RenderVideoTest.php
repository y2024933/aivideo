<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Jobs\PollRemotionRenderJob;
use App\Models\Product;
use App\Services\Contracts\VideoEditorContract;
use App\Services\Stubs\StubVideoEditor;

// 原本測 /api/cases/* 的 HTTP endpoint，P0 已移除 Vue SPA 與該組 route，
// 改成直接測 VideoEditorContract 與輪詢 Job 的行為。完整上架流程測試留給 P10。

function makeRenderingProduct(array $attributes = []): Product
{
    return Product::create(['title' => '渲染測試商品', 'status' => ProductStatus::Rendering, ...$attributes]);
}

it('stub video editor returns a render_id', function () {
    $result = (new StubVideoEditor())->submitRender(makeRenderingProduct());

    expect($result['render_id'])->toStartWith('stub_render_');
});

it('propagates exceptions thrown by the video editor', function () {
    $editor = new class implements VideoEditorContract
    {
        public function submitRender(Product $product): array
        {
            throw new RuntimeException('Lambda invocation failed');
        }

        public function queryRenderStatus(string $renderId): array
        {
            return ['status' => 'failed', 'video_url' => null, 'error' => 'fail'];
        }
    };

    expect(fn () => $editor->submitRender(makeRenderingProduct()))
        ->toThrow(RuntimeException::class, 'Lambda invocation failed');
});

it('moves product to final_pending_review when render completes', function () {
    $product = makeRenderingProduct(['render_id' => 'render_123']);

    (new PollRemotionRenderJob($product->id, 'render_123'))->handle(new StubVideoEditor());

    $product->refresh();
    expect($product->status)->toBe(ProductStatus::FinalPendingReview);
    expect($product->final_video_remote_url)->toBe('https://placehold.co/1080x1920.mp4');
    expect($product->render_id)->toBeNull();
});

it('moves product to render_failed and writes status_message when render fails', function () {
    $product = makeRenderingProduct(['render_id' => 'render_fail']);

    $editor = Mockery::mock(VideoEditorContract::class);
    $editor->shouldReceive('queryRenderStatus')->once()->andReturn([
        'status' => 'failed',
        'video_url' => null,
        'error' => 'OOM in Lambda',
    ]);

    (new PollRemotionRenderJob($product->id, 'render_fail'))->handle($editor);

    $product->refresh();
    expect($product->status)->toBe(ProductStatus::RenderFailed);
    expect($product->status_message)->toBe('OOM in Lambda');
    expect($product->render_id)->toBeNull();
});

it('moves product to render_failed on polling timeout', function () {
    $product = makeRenderingProduct(['render_id' => 'render_slow']);

    $editor = Mockery::mock(VideoEditorContract::class);
    $editor->shouldNotReceive('queryRenderStatus');

    (new PollRemotionRenderJob($product->id, 'render_slow', 20))->handle($editor);

    $product->refresh();
    expect($product->status)->toBe(ProductStatus::RenderFailed);
    expect($product->status_message)->toBe('影片渲染逾時');
});
