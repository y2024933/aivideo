<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Jobs\DownloadProductImagesJob;
use App\Models\Product;
use App\Services\Shopee\ShopeeLinkParser;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 * 圖片下載。走 default queue：純 HTTP GET 不需要瀏覽器，擠在併發 1 的 browser
 * queue 只會讓下一個商品等上好幾分鐘。
 */

function imageBytes(int $width = 1000, int $height = 1000): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

function runDownload(string $productId, array $urls = []): void
{
    app()->call([new DownloadProductImagesJob($productId, $urls), 'handle']);
}

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('s3');
    config(['video.autopilot.product' => false, 'services.shopee.image_cdn' => 'https://cdn.example.test/file/']);

    Http::fake(['https://cdn.example.test/*' => Http::response(imageBytes(), 200, ['Content-Type' => 'image/png'])]);

    $this->product = Product::factory()->shopee(111, 222)->status(ProductStatus::ProductPendingReview)->create();
});

it('writes full metadata and an s3 copy for every image', function () {
    runDownload($this->product->id, [
        'https://cdn.example.test/file/hash-a',
        'https://cdn.example.test/file/hash-b',
    ]);

    $images = $this->product->images()->get();

    expect($images)->toHaveCount(2);

    $images->each(function ($image) {
        expect($image->status)->toBe('done')
            // Remotion Lambda 只讀 remote_url，缺它渲染必 404
            ->and($image->remote_url)->not->toBeNull()
            ->and($image->width)->toBe(1000)
            ->and($image->height)->toBe(1000)
            ->and($image->bytes)->toBeGreaterThan(0)
            ->and($image->mime)->toBe('image/png')
            // 蝦皮商品圖的著作權在賣家，未取得授權前一律 unverified
            ->and($image->license_status)->toBe('unverified');

        Storage::disk('s3')->assertExists($image->local_path ? ltrim(str_replace('/storage/', '', $image->local_path), '/') : 'missing');
    });

    expect($images->where('is_primary', true))->toHaveCount(1);
});

it('sends a shopee referer and asks for the original image', function () {
    // CDN 少了 Referer 會回 403；縮圖拉成 1080x1920 會糊成一團
    runDownload($this->product->id, ['https://cdn.example.test/file/hash-a@resize_w900_nl.webp']);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://cdn.example.test/file/hash-a'
        && $request->header('Referer') === [config('services.shopee.referer')]);
});

it('dedupes by image hash so a rerun does not duplicate rows', function () {
    runDownload($this->product->id, ['https://cdn.example.test/file/hash-a']);
    runDownload($this->product->id, ['https://cdn.example.test/file/hash-a']);

    expect($this->product->images()->count())->toBe(1);
});

it('marks a single failed image without failing the whole batch', function () {
    // ⚠️ 壞圖刻意換成另一個 host：Http::fake() 是「後加不覆蓋」，beforeEach 已經註冊的
    //    cdn.example.test/* 萬用規則會先比中，同 host 的 404 永遠不會生效
    Http::fake(['https://broken.example.test/file/bad' => Http::response('nope', 404)]);

    runDownload($this->product->id, ['https://cdn.example.test/file/good', 'https://broken.example.test/file/bad']);

    $images = $this->product->images()->get();

    expect($images)->toHaveCount(2)
        ->and($images->firstWhere('image_hash', 'good')->status)->toBe('done')
        ->and($images->firstWhere('image_hash', 'bad')->status)->toBe('failed')
        // 下載失敗的圖不可以留在勾選清單裡，否則 checkpoint ① 會被一張不存在的圖卡住
        ->and($images->firstWhere('image_hash', 'bad')->is_selected)->toBeFalse()
        ->and($images->firstWhere('image_hash', 'bad')->error_message)->toContain('404');
});

it('caps the batch so a 50 image listing does not burn bandwidth', function () {
    $urls = collect(range(1, 30))->map(fn (int $i) => "https://cdn.example.test/file/hash-{$i}")->all();

    runDownload($this->product->id, $urls);

    expect($this->product->images()->count())->toBe(12);
});

it('falls back to the pending rows when no urls are passed', function () {
    $this->product->images()->create([
        'source' => 'shopee',
        'source_url' => 'https://cdn.example.test/file/pending-one',
        'image_hash' => 'pending-one',
        'status' => 'pending',
    ]);

    runDownload($this->product->id);

    expect($this->product->images()->sole()->status)->toBe('done');
});

it('hands control back to the pipeline once the images are in place', function () {
    // ⚠️ 放行判斷必須等圖片落地才做：approvalBlockers 會檢查 remote_url，
    //    在抓完商品資料時就判斷的話永遠是「圖片不足 2 張」
    Queue::fake();
    config(['video.autopilot.product' => true]);

    runDownload($this->product->id, [
        'https://cdn.example.test/file/hash-a',
        'https://cdn.example.test/file/hash-b',
    ]);

    expect($this->product->refresh()->status)->toBe(ProductStatus::ProductApproved);
});

it('runs on the default queue', function () {
    expect((new DownloadProductImagesJob('x'))->queue)->toBe('default');
});

it('strips resize suffixes consistently with the link parser', function () {
    expect(ShopeeLinkParser::originalImageUrl('https://cdn.example.test/file/abc@resize_w450'))
        ->toBe('https://cdn.example.test/file/abc');
});
