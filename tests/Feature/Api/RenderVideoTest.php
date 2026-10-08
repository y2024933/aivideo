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

// ─── 以下為 P10 補的輪詢路徑測試 ───
//
// 原本的 'propagates exceptions thrown by the video editor' 驗的是測試自己當場定義的
// 匿名類別會不會丟例外（必然成立），完全沒碰到 production 程式。真正該守的是
// PollRemotionRenderJob 對「查詢失敗」與「還在渲染」這兩種回應的處理。

it('retries the query instead of failing the render when the status call blows up', function () {
    $product = makeRenderingProduct(['render_id' => 'render_flaky']);

    // 前兩次查詢丟例外，第三次才回 completed
    $editor = new class implements VideoEditorContract
    {
        public int $calls = 0;

        public function submitRender(Product $product): array
        {
            return ['render_id' => 'render_flaky'];
        }

        public function queryRenderStatus(string $renderId): array
        {
            if (++$this->calls < 3) {
                throw new RuntimeException('Lambda 503');
            }

            return ['status' => 'completed', 'video_url' => 'https://placehold.co/1080x1920.mp4', 'error' => null];
        }
    };

    app()->instance(VideoEditorContract::class, $editor);
    (new PollRemotionRenderJob($product->id, 'render_flaky'))->handle($editor);

    // 暫時性的查詢失敗不可以把整支渲染判死（Lambda 還在跑，錢已經花了）
    expect($editor->calls)->toBe(3)
        ->and($product->refresh()->status)->toBe(ProductStatus::FinalPendingReview)
        ->and($product->final_video_remote_url)->toBe('https://placehold.co/1080x1920.mp4')
        ->and($product->status_message)->toBeNull()
        ->and($product->statusHistory()->pluck('to_status')->all())->toBe(['final_pending_review']);
});

it('never writes a rendering to rendering transition while polling', function () {
    // 回歸測試：舊版在「還在渲染」時會把狀態轉回原狀態，rendering → rendering
    // 讓商品永遠停在渲染中而輪詢永遠不結束（ProductStatus::TRANSITIONS 現已禁止）。
    $product = makeRenderingProduct(['render_id' => 'render_slow']);

    $editor = Mockery::mock(VideoEditorContract::class);
    $editor->shouldReceive('queryRenderStatus')->times(20)
        ->andReturn(['status' => 'rendering', 'video_url' => null, 'error' => null]);

    app()->instance(VideoEditorContract::class, $editor);
    (new PollRemotionRenderJob($product->id, 'render_slow'))->handle($editor);

    $product->refresh();

    // 20 次輪詢後才認輸，而且中間沒有留下任何狀態變更紀錄
    expect($product->status)->toBe(ProductStatus::RenderFailed)
        ->and($product->status_message)->toBe('影片渲染逾時')
        ->and($product->statusHistory()->pluck('to_status')->all())->toBe(['render_failed'])
        ->and($product->statusHistory()->where('from_status', 'rendering')->where('to_status', 'rendering')->count())->toBe(0);
});

it('ignores a stale poll for a render that already finished', function () {
    $product = makeRenderingProduct(['final_video_url' => 'https://placehold.co/done.mp4']);

    $editor = Mockery::mock(VideoEditorContract::class);
    $editor->shouldNotReceive('queryRenderStatus');

    (new PollRemotionRenderJob($product->id, 'render_old'))->handle($editor);

    expect($product->refresh()->status)->toBe(ProductStatus::Rendering)
        ->and($product->statusHistory()->count())->toBe(0);
});

it('does nothing for a deleted product', function () {
    $editor = Mockery::mock(VideoEditorContract::class);
    $editor->shouldNotReceive('queryRenderStatus');

    (new PollRemotionRenderJob('00000000-0000-0000-0000-000000000000', 'render_gone'))->handle($editor);

    expect(Product::count())->toBe(0);
});
