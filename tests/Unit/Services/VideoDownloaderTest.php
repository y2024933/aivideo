<?php

declare(strict_types=1);

use App\Services\VideoDownloader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * B-roll 影片下載。與 ImageDownloader 同樣「本地 + S3 雙寫」：
 * Remotion Lambda 只讀得到 S3 的絕對 URL，少了那一步渲染時影片一定 404。
 *
 * ⚠️ Http::fake() 全程生效，不會真的連 Kling／Dola 的 CDN。
 */
beforeEach(function () {
    Storage::fake('public');
    Storage::fake('s3');
});

it('writes the video to both the public disk and s3', function () {
    Http::fake(['https://cdn.kling.test/a.mp4' => Http::response('fake-mp4-bytes', 200, ['Content-Type' => 'video/mp4'])]);

    $file = VideoDownloader::download('https://cdn.kling.test/a.mp4');

    Storage::disk('public')->assertExists($file->diskPath());
    // 這條是「Lambda 拿得到影片」的前提，不要只驗本地
    Storage::disk('s3')->assertExists($file->diskPath());

    expect(Storage::disk('public')->get($file->diskPath()))->toBe('fake-mp4-bytes')
        ->and(Storage::disk('s3')->get($file->diskPath()))->toBe('fake-mp4-bytes');
});

it('returns a DownloadedFile with the fields the shot columns need', function () {
    Http::fake(['*' => Http::response('0123456789', 200, ['Content-Type' => 'video/mp4'])]);

    $file = VideoDownloader::download('https://cdn.kling.test/a.mp4');

    expect($file->localPath)->toStartWith('/storage/videos/')->toEndWith('.mp4')
        ->and($file->remoteUrl)->toBe(Storage::disk('s3')->url($file->diskPath()))
        ->and($file->remoteUrl)->toContain($file->diskPath())
        ->and($file->bytes)->toBe(10)
        ->and($file->mime)->toBe('video/mp4')
        // 影片不解析尺寸（Remotion 自己讀），這兩欄刻意留 null
        ->and($file->width)->toBeNull()
        ->and($file->height)->toBeNull()
        ->and($file->diskPath())->toBe(ltrim(str_replace('/storage/', '', $file->localPath), '/'));
});

it('defaults to the videos directory and honours an override', function () {
    Http::fake(['*' => Http::response('bytes', 200)]);

    expect(VideoDownloader::download('https://cdn.kling.test/a.mp4')->localPath)->toStartWith('/storage/videos/')
        ->and(VideoDownloader::download('https://cdn.kling.test/a.mp4', 'broll/2026')->localPath)->toStartWith('/storage/broll/2026/')
        // 前後斜線會被修掉，不會產生 //
        ->and(VideoDownloader::download('https://cdn.kling.test/a.mp4', '/broll/')->localPath)
        ->toMatch('#^/storage/broll/[0-9a-f-]{36}\.mp4$#');
});

it('always forces the mp4 extension regardless of the source url', function () {
    // Kling 的下載連結常帶一串 query string 或 .webm 後綴，但我們一律當 mp4 存
    Http::fake(['*' => Http::response('bytes', 200, ['Content-Type' => 'video/webm'])]);

    $file = VideoDownloader::download('https://cdn.kling.test/task/abc.webm?sig=xyz');

    expect($file->localPath)->toEndWith('.mp4')
        // mime 仍回報來源真實值，不被檔名覆蓋
        ->and($file->mime)->toBe('video/webm');
});

it('uses a unique filename per download so two shots never collide', function () {
    Http::fake(['*' => Http::response('bytes', 200)]);

    $first = VideoDownloader::download('https://cdn.kling.test/same.mp4');
    $second = VideoDownloader::download('https://cdn.kling.test/same.mp4');

    expect($first->localPath)->not->toBe($second->localPath);

    Storage::disk('s3')->assertExists($first->diskPath());
    Storage::disk('s3')->assertExists($second->diskPath());
});

it('falls back to video/mp4 when the response has no content type', function () {
    Http::fake(['*' => Http::response('bytes', 200)]);

    expect(VideoDownloader::download('https://cdn.kling.test/a.mp4')->mime)->toBe('video/mp4');
});

it('throws on a failed download and writes nothing', function (int $status) {
    Http::fake(['*' => Http::response('nope', $status)]);

    expect(fn () => VideoDownloader::download('https://cdn.kling.test/missing.mp4'))
        ->toThrow(RuntimeException::class, "影片下載失敗：HTTP {$status}");

    // 失敗時不可留下 0 byte 的殘檔騙過後續的 renderableUrl()
    expect(Storage::disk('public')->allFiles())->toBe([])
        ->and(Storage::disk('s3')->allFiles())->toBe([]);
})->with([403, 404, 500, 503]);

it('does not swallow s3 failures', function () {
    // 靜默產生 remote_url = null 遠比當下丟 exception 難 debug，所以 s3.throw 必須保持 true
    Http::fake(['*' => Http::response('bytes', 200)]);

    Storage::forgetDisk('s3');
    config(['filesystems.disks.s3' => ['driver' => 'local', 'root' => '/dev/null/unwritable', 'throw' => true]]);

    expect(fn () => VideoDownloader::download('https://cdn.kling.test/a.mp4'))
        ->toThrow(League\Flysystem\UnableToCreateDirectory::class);
});

it('sends a plain get with a generous timeout', function () {
    Http::fake(['*' => Http::response('bytes', 200)]);

    VideoDownloader::download('https://cdn.kling.test/a.mp4');

    // 影片檔遠比圖片大，timeout 120 秒是刻意的；這裡只驗請求本身沒被改成 POST／帶 body
    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && $request->url() === 'https://cdn.kling.test/a.mp4'
        && $request->body() === '');
});
