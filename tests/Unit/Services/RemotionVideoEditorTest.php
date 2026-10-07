<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\Shot;
use App\Services\RemotionVideoEditor;
use Remotion\LambdaPhp\GetRenderProgressResponse;
use Remotion\LambdaPhp\PHPClient;
use Remotion\LambdaPhp\RenderMediaOnLambdaResponse;
use Remotion\LambdaPhp\RenderParams;

beforeEach(function () {
    config([
        'services.remotion.function_name' => 'remotion-render-test',
        'services.remotion.serve_url' => 'https://remotionlambda-us-east-1.s3.us-east-1.amazonaws.com/sites/aivideo/index.html',
        'services.remotion.region' => 'us-east-1',
        'services.remotion.key' => 'test-key',
        'services.remotion.secret' => 'test-secret',
    ]);
});

it('submitRender returns render_id from Lambda response', function () {
    $renderResponse = new RenderMediaOnLambdaResponse();
    $renderResponse->type = 'success';
    $renderResponse->renderId = 'render_abc123';
    $renderResponse->bucketName = 'remotionlambda-useast1-abc';

    $mockClient = Mockery::mock(PHPClient::class);
    $mockClient->shouldReceive('renderMediaOnLambda')
        ->once()
        ->andReturn($renderResponse);

    $editor = new RemotionVideoEditor();
    $editor->setClient($mockClient);

    $product = Product::create(['title' => '渲染測試商品']);
    $product->shots()->create([
        'shot_id' => 'S01',
        'shot_order' => 1,
        'video_url' => '/storage/videos/v1.mp4',
        'video_remote_url' => 'https://cdn.example.com/v1.mp4',
        'video_status' => 'done',
        'duration_seconds' => 5,
        'subtitle' => '測試字幕',
        'voiceover_url' => '/storage/voiceover/test.mp3',
        'voiceover_remote_url' => 'https://cdn.example.com/voiceover/test.mp3',
        'voiceover_status' => 'done',
    ]);

    $result = $editor->submitRender($product);

    $decoded = json_decode($result['render_id'], true);
    expect($decoded['renderId'])->toBe('render_abc123');
    expect($decoded['bucketName'])->toBe('remotionlambda-useast1-abc');
});

it('queryRenderStatus returns completed when render is done', function () {
    $progressResponse = new GetRenderProgressResponse();
    $progressResponse->done = true;
    $progressResponse->overallProgress = 1.0;
    $progressResponse->fatalErrorEncountered = false;
    $progressResponse->outputFile = 'https://s3.example.com/output.mp4';
    $progressResponse->chunks = 10;
    $progressResponse->lambdasInvoked = 5;
    $progressResponse->renderSize = 1024000;
    $progressResponse->currentTime = 1000;
    $progressResponse->timeToFinish = null;
    $progressResponse->outBucket = 'remotionlambda-useast1-abc';
    $progressResponse->outKey = 'renders/render_abc123/out.mp4';
    $progressResponse->bucket = 'remotionlambda-useast1-abc';
    $progressResponse->type = 'progress';

    $mockClient = Mockery::mock(PHPClient::class);
    $mockClient->shouldReceive('getRenderProgress')
        ->once()
        ->with('render_abc123', 'remotionlambda-useast1-abc')
        ->andReturn($progressResponse);

    $editor = new RemotionVideoEditor();
    $editor->setClient($mockClient);

    $renderData = json_encode(['renderId' => 'render_abc123', 'bucketName' => 'remotionlambda-useast1-abc']);
    $result = $editor->queryRenderStatus($renderData);

    expect($result['status'])->toBe('completed');
    expect($result['video_url'])->toBe('https://s3.example.com/output.mp4');
    expect($result['error'])->toBeNull();
});

it('queryRenderStatus returns failed when fatal error encountered', function () {
    $progressResponse = new GetRenderProgressResponse();
    $progressResponse->done = false;
    $progressResponse->overallProgress = 0.3;
    $progressResponse->fatalErrorEncountered = true;
    $progressResponse->outputFile = null;
    $progressResponse->chunks = 3;
    $progressResponse->lambdasInvoked = 5;
    $progressResponse->renderSize = 0;
    $progressResponse->currentTime = 500;
    $progressResponse->timeToFinish = null;
    $progressResponse->outBucket = null;
    $progressResponse->outKey = null;
    $progressResponse->bucket = 'remotionlambda-useast1-abc';
    $progressResponse->type = 'progress';

    $mockClient = Mockery::mock(PHPClient::class);
    $mockClient->shouldReceive('getRenderProgress')
        ->once()
        ->andReturn($progressResponse);

    $editor = new RemotionVideoEditor();
    $editor->setClient($mockClient);

    $renderData = json_encode(['renderId' => 'render_fail', 'bucketName' => 'remotionlambda-useast1-abc']);
    $result = $editor->queryRenderStatus($renderData);

    expect($result['status'])->toBe('failed');
    expect($result['error'])->toBe('Remotion render encountered a fatal error');
});

it('queryRenderStatus returns rendering when in progress', function () {
    $progressResponse = new GetRenderProgressResponse();
    $progressResponse->done = false;
    $progressResponse->overallProgress = 0.5;
    $progressResponse->fatalErrorEncountered = false;
    $progressResponse->outputFile = null;
    $progressResponse->chunks = 5;
    $progressResponse->lambdasInvoked = 5;
    $progressResponse->renderSize = 512000;
    $progressResponse->currentTime = 500;
    $progressResponse->timeToFinish = 10;
    $progressResponse->outBucket = null;
    $progressResponse->outKey = null;
    $progressResponse->bucket = 'remotionlambda-useast1-abc';
    $progressResponse->type = 'progress';

    $mockClient = Mockery::mock(PHPClient::class);
    $mockClient->shouldReceive('getRenderProgress')
        ->once()
        ->andReturn($progressResponse);

    $editor = new RemotionVideoEditor();
    $editor->setClient($mockClient);

    $renderData = json_encode(['renderId' => 'render_pending', 'bucketName' => 'remotionlambda-useast1-abc']);
    $result = $editor->queryRenderStatus($renderData);

    expect($result['status'])->toBe('rendering');
    expect($result['video_url'])->toBeNull();
});

it('throws RuntimeException when function_name is missing', function () {
    config(['services.remotion.function_name' => null]);
    new RemotionVideoEditor();
})->throws(RuntimeException::class, 'REMOTION_FUNCTION_NAME is not configured');

it('throws RuntimeException when serve_url is missing', function () {
    config(['services.remotion.serve_url' => null]);
    new RemotionVideoEditor();
})->throws(RuntimeException::class, 'REMOTION_SERVE_URL is not configured');

// --- inputProps 契約 ---

/**
 * buildInputProps 是 private：它的輸出就是「PHP 與 Remotion bundle 之間的契約」，
 * 用反射直接驗證比繞過 Lambda mock 去猜更穩。
 */
function inputPropsFor(Product $product): array
{
    $editor = new RemotionVideoEditor();
    $method = new ReflectionMethod($editor, 'buildInputProps');
    $method->setAccessible(true);

    return $method->invoke($editor, $product);
}

it('renders the ProductVideo composition, not the pre-P4 composition id', function () {
    $renderResponse = new RenderMediaOnLambdaResponse();
    $renderResponse->type = 'success';
    $renderResponse->renderId = 'render_abc123';
    $renderResponse->bucketName = 'remotionlambda-useast1-abc';

    $captured = null;
    $mockClient = Mockery::mock(PHPClient::class);
    $mockClient->shouldReceive('renderMediaOnLambda')
        ->once()
        ->with(Mockery::on(function (RenderParams $params) use (&$captured) {
            $captured = $params;

            return true;
        }))
        ->andReturn($renderResponse);

    $editor = new RemotionVideoEditor();
    $editor->setClient($mockClient);
    $editor->submitRender(Product::factory()->renderable(2)->create());

    expect($captured->getComposition())->toBe('ProductVideo');
});

it('carries the build tag so a stale Lambda bundle fails loudly', function () {
    expect(inputPropsFor(Product::factory()->renderable(2)->create())['expectBuildTag'])->toBe('v2-kenburns');
});

it('drops the props that no JSX consumer reads', function () {
    $props = inputPropsFor(Product::factory()->renderable(2)->create());

    expect($props)->not->toHaveKey('clipDurationSec')
        ->and($props)->not->toHaveKey('brand')
        ->and($props)->not->toHaveKey('isPublicFacility')
        ->and($props)->not->toHaveKey('publicFacilityLabel')
        ->and($props['shots'][0])->not->toHaveKey('clipDurationSec')
        ->and($props['shots'][0])->not->toHaveKey('isPublicFacility');
});

it('sends the s3 url for images, never a local path', function () {
    $product = Product::factory()->renderable(2)->create();
    $shot = $product->shots()->first();

    $props = inputPropsFor($product);

    expect($props['shots'][0]['kind'])->toBe('image')
        ->and($props['shots'][0]['imageUrl'])->toBe($shot->image_remote_url)
        ->and($props['shots'][0]['imageUrl'])->toStartWith('http')
        ->and($props['shots'][0]['imageUrl'])->not->toBe($shot->image_url);
});

it('marks a shot with a finished b-roll as a video shot', function () {
    $product = Product::factory()->renderable(2)->create();
    $shot = $product->shots()->first();
    $shot->update([
        'video_provider' => 'kling',
        'video_url' => '/storage/videos/shot-broll.mp4',
        'video_remote_url' => 'https://remotionlambda-test.s3.amazonaws.com/videos/shot-broll.mp4',
        'video_status' => 'done',
    ]);

    $props = inputPropsFor($product->fresh());

    expect($props['shots'][0]['kind'])->toBe('video')
        ->and($props['shots'][0]['videoUrl'])->toBe($shot->fresh()->video_remote_url);
});

it('skips shots without any remote url (Lambda cannot read localhost)', function () {
    $product = Product::factory()->renderable(3)->create();
    $product->shots()->first()->update(['image_remote_url' => null, 'video_remote_url' => null]);

    expect(inputPropsFor($product->fresh())['shots'])->toHaveCount(2);
});

it('omits voiceover urls unless audio_mode is tts', function () {
    $product = Product::factory()->renderable(2)->create(['audio_mode' => 'none']);
    $product->shots->each(fn (Shot $shot) => $shot->update([
        'voiceover_text' => '降噪開啟後，世界瞬間安靜。',
        'voiceover_url' => '/storage/voiceovers/shot.mp3',
        'voiceover_remote_url' => 'https://remotionlambda-test.s3.amazonaws.com/voiceovers/shot.mp3',
        'voiceover_status' => 'done',
    ]));

    expect(collect(inputPropsFor($product->fresh())['shots'])->pluck('voiceoverUrl')->all())
        ->toBe([null, null]);

    $product->update(['audio_mode' => 'tts']);

    expect(inputPropsFor($product->fresh())['shots'][0]['voiceoverUrl'])
        ->toBe($product->shots()->first()->voiceover_remote_url)
        ->toStartWith('http');
});

it('omits the bgm track when there is no remote bgm url', function () {
    $product = Product::factory()->renderable(2)->create(['bgm_remote_url' => null]);

    expect(inputPropsFor($product)['bgm'])->toBeNull();

    $product->update(['bgm_remote_url' => 'https://cdn.example.com/bgm/calm.mp3', 'bgm_volume' => 0.2]);

    expect(inputPropsFor($product->fresh())['bgm'])
        ->toBe(['audioUrl' => 'https://cdn.example.com/bgm/calm.mp3', 'volume' => 0.2]);
});

it('falls back to the compliance watermark when there is no disclosure prefix', function () {
    $product = Product::factory()->renderable(2)->create(['disclosure_prefix' => null]);

    expect(inputPropsFor($product)['watermark']['text'])->toBe(config('compliance.disclosure_watermark'));
});

it('passes ken burns and fit through per shot', function () {
    $product = Product::factory()->renderable(2)->create(['default_ken_burns' => 'panLeft']);
    $product->shots()->first()->update(['ken_burns' => null, 'fit' => 'cover']);

    $props = inputPropsFor($product->fresh());

    expect($props['shots'][0]['kenBurns'])->toBe('panLeft')
        ->and($props['shots'][0]['fit'])->toBe('cover')
        ->and($props['fps'])->toBe(config('video.fps'))
        ->and($props['subtitleSettings'])->toEqual($product->subtitle_settings);
});

it('never falls back to voiceover_text for the subtitle', function () {
    $product = Product::factory()->renderable(2)->create();
    $product->shots()->first()->update(['subtitle' => null, 'voiceover_text' => '這段是口語配音稿，拿去當字幕會爆版']);

    expect(inputPropsFor($product->fresh())['shots'][0]['subtitle'])->toBe('');
});
