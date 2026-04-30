<?php

declare(strict_types=1);

use App\Models\BuildingCase;
use App\Services\RemotionVideoEditor;
use Aws\Lambda\LambdaClient;
use Aws\Result;
use GuzzleHttp\Psr7\Stream;

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
    $mockPayload = json_encode(['renderId' => 'render_abc123']);
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $mockPayload);
    rewind($stream);

    $mockLambda = Mockery::mock(LambdaClient::class);
    $mockLambda->shouldReceive('invoke')
        ->once()
        ->withArgs(function ($args) {
            $payload = json_decode($args['Payload'], true);
            // 驗證 payload 包含 version 欄位
            $hasVersion = isset($payload['version']) && $payload['version'] === '4.0.454';
            // 驗證 inputProps shots 使用 remote URL
            $usesRemoteUrl = isset($payload['inputProps']['shots'][0]['videoUrl'])
                && $payload['inputProps']['shots'][0]['videoUrl'] === 'https://cdn.example.com/v1.mp4';
            return $args['FunctionName'] === 'remotion-render-test'
                && $payload['type'] === 'start'
                && $payload['composition'] === 'BuildingVideo'
                && $hasVersion
                && $usesRemoteUrl;
        })
        ->andReturn(new Result(['Payload' => new Stream($stream)]));

    $editor = new RemotionVideoEditor();
    // 透過 Reflection 注入 mock
    $ref = new ReflectionProperty($editor, 'lambda');
    $ref->setValue($editor, $mockLambda);

    $case = BuildingCase::create(['name' => '渲染測試']);
    $case->shots()->create([
        'shot_id' => 'S01',
        'shot_order' => 1,
        'flux_prompt' => 'test',
        'video_url' => '/storage/videos/v1.mp4',
        'video_remote_url' => 'https://cdn.example.com/v1.mp4',
        'video_status' => 'done',
        'duration_seconds' => 5,
        'subtitle' => '測試字幕',
        'voiceover_url' => '/storage/voiceover/test.mp3',
        'voiceover_remote_url' => 'https://cdn.example.com/voiceover/test.mp3',
        'voiceover_status' => 'done',
    ]);

    $result = $editor->submitRender($case);

    expect($result)->toBe(['render_id' => 'render_abc123']);
});

it('queryRenderStatus returns completed when Lambda returns success', function () {
    $mockPayload = json_encode([
        'type' => 'success',
        'outputUrl' => 'https://s3.example.com/output.mp4',
    ]);
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $mockPayload);
    rewind($stream);

    $mockLambda = Mockery::mock(LambdaClient::class);
    $mockLambda->shouldReceive('invoke')
        ->once()
        ->andReturn(new Result(['Payload' => new Stream($stream)]));

    $editor = new RemotionVideoEditor();
    $ref = new ReflectionProperty($editor, 'lambda');
    $ref->setValue($editor, $mockLambda);

    $result = $editor->queryRenderStatus('render_abc123');

    expect($result['status'])->toBe('completed');
    expect($result['video_url'])->toBe('https://s3.example.com/output.mp4');
    expect($result['error'])->toBeNull();
});

it('queryRenderStatus returns failed when Lambda returns error', function () {
    $mockPayload = json_encode([
        'type' => 'error',
        'message' => 'Out of memory',
    ]);
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $mockPayload);
    rewind($stream);

    $mockLambda = Mockery::mock(LambdaClient::class);
    $mockLambda->shouldReceive('invoke')
        ->once()
        ->andReturn(new Result(['Payload' => new Stream($stream)]));

    $editor = new RemotionVideoEditor();
    $ref = new ReflectionProperty($editor, 'lambda');
    $ref->setValue($editor, $mockLambda);

    $result = $editor->queryRenderStatus('render_fail');

    expect($result['status'])->toBe('failed');
    expect($result['error'])->toBe('Out of memory');
});

it('queryRenderStatus returns rendering when Lambda returns pending', function () {
    $mockPayload = json_encode(['type' => 'progress', 'progress' => 0.5]);
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $mockPayload);
    rewind($stream);

    $mockLambda = Mockery::mock(LambdaClient::class);
    $mockLambda->shouldReceive('invoke')
        ->once()
        ->andReturn(new Result(['Payload' => new Stream($stream)]));

    $editor = new RemotionVideoEditor();
    $ref = new ReflectionProperty($editor, 'lambda');
    $ref->setValue($editor, $mockLambda);

    $result = $editor->queryRenderStatus('render_pending');

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
