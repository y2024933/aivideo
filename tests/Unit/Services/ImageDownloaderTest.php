<?php

declare(strict_types=1);

use App\Services\ImageDownloader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** 產生真實 PNG 位元組，才測得出 getimagesizefromstring 的 width/height/mime */
function fakePng(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('s3');
    $this->downloader = new ImageDownloader();
});

it('writes to both public disk and s3 and fills metadata', function () {
    $png = fakePng(1000, 1000);
    Http::fake(['https://cdn.example.com/a.png' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);

    $file = $this->downloader->download('https://cdn.example.com/a.png', 'products');

    expect($file->localPath)->toStartWith('/storage/products/')->toEndWith('.png')
        ->and($file->remoteUrl)->not->toBeNull()
        ->and($file->width)->toBe(1000)
        ->and($file->height)->toBe(1000)
        ->and($file->mime)->toBe('image/png')
        ->and($file->bytes)->toBe(strlen($png));

    Storage::disk('public')->assertExists($file->diskPath());
    // Remotion Lambda 只讀得到 S3，這條是 Ken Burns 不 404 的前提
    Storage::disk('s3')->assertExists($file->diskPath());
});

// 靜默產生 remote_url = null 遠比當下丟 exception 難 debug，所以 s3.throw 必須保持 true
it('does not swallow s3 failures', function () {
    Http::fake(['https://cdn.example.com/a.png' => Http::response(fakePng(10, 10), 200, ['Content-Type' => 'image/png'])]);

    Storage::forgetDisk('s3');
    config(['filesystems.disks.s3' => ['driver' => 'local', 'root' => '/dev/null/unwritable', 'throw' => true]]);

    expect(fn () => $this->downloader->download('https://cdn.example.com/a.png'))
        ->toThrow(League\Flysystem\UnableToCreateDirectory::class);
});

it('sends a shopee referer and requests the original image', function () {
    Http::fake(['*' => Http::response(fakePng(1080, 1920), 200, ['Content-Type' => 'image/webp'])]);

    $file = ImageDownloader::shopee('https://down-tw.img.susercontent.com/file/abc123@resize_w900_nl.webp');

    expect($file->height)->toBe(1920);

    Http::assertSent(fn ($request) => $request->url() === 'https://down-tw.img.susercontent.com/file/abc123'
        && $request->header('Referer') === [config('services.shopee.referer')]);
});

it('throws on a failed download', function () {
    Http::fake(['https://cdn.example.com/missing.png' => Http::response('nope', 404)]);

    expect(fn () => $this->downloader->download('https://cdn.example.com/missing.png'))
        ->toThrow(RuntimeException::class, 'HTTP 404');
});

it('adopts an already uploaded public file and syncs it to s3', function () {
    Storage::disk('public')->put('products/manual.png', fakePng(800, 600));

    $file = $this->downloader->adoptPublicFile('products/manual.png');

    expect($file->localPath)->toBe('/storage/products/manual.png')
        ->and($file->width)->toBe(800)
        ->and($file->height)->toBe(600)
        ->and($file->remoteUrl)->not->toBeNull();

    Storage::disk('s3')->assertExists('products/manual.png');
});
