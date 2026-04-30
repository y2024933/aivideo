<?php

declare(strict_types=1);

use App\Models\BuildingCase;
use App\Services\RemotionVideoEditor;
use Remotion\LambdaPhp\GetRenderProgressResponse;
use Remotion\LambdaPhp\PHPClient;
use Remotion\LambdaPhp\RenderMediaOnLambdaResponse;

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
