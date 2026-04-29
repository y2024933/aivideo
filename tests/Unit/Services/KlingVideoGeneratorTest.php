<?php

declare(strict_types=1);

use App\Services\KlingVideoGenerator;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.kling.access_key' => 'test-ak-123',
        'services.kling.secret_key' => 'test-sk-456-must-be-at-least-32-bytes-long',
    ]);
});

it('submits image to video and returns task_id', function () {
    Http::fake([
        'api-beijing.klingai.com/v1/videos/image2video' => Http::response([
            'code' => 0,
            'data' => ['task_id' => 'task_abc123'],
        ]),
    ]);

    $generator = new KlingVideoGenerator();
    $result = $generator->submitImageToVideo('https://example.com/image.jpg', 'a cat walking');

    expect($result)->toBe(['task_id' => 'task_abc123']);
});

it('queries task status and returns succeed result', function () {
    Http::fake([
        'api-beijing.klingai.com/v1/videos/image2video/task_abc123' => Http::response([
            'code' => 0,
            'data' => [
                'task_status' => 'succeed',
                'task_status_msg' => null,
                'task_result' => [
                    'videos' => [['url' => 'https://kling.ai/video/output.mp4']],
                ],
            ],
        ]),
    ]);

    $generator = new KlingVideoGenerator();
    $result = $generator->queryTaskStatus('task_abc123');

    expect($result['status'])->toBe('succeed');
    expect($result['video_url'])->toBe('https://kling.ai/video/output.mp4');
});

it('returns failed status when API returns error', function () {
    Http::fake([
        'api-beijing.klingai.com/v1/videos/image2video/task_fail' => Http::response([
            'code' => 1001,
            'message' => 'task not found',
        ], 400),
    ]);

    $generator = new KlingVideoGenerator();
    $result = $generator->queryTaskStatus('task_fail');

    expect($result['status'])->toBe('failed');
    expect($result['error'])->toContain('HTTP 400');
});

it('throws RuntimeException when submit fails', function () {
    Http::fake([
        'api-beijing.klingai.com/v1/videos/image2video' => Http::response(['code' => 500], 500),
    ]);

    $generator = new KlingVideoGenerator();
    $generator->submitImageToVideo('https://example.com/img.jpg', 'fail test');
})->throws(RuntimeException::class, 'Kling submit failed');

it('sends JWT Authorization header with Bearer prefix', function () {
    Http::fake([
        'api-beijing.klingai.com/v1/videos/image2video' => Http::response([
            'code' => 0,
            'data' => ['task_id' => 'task_jwt'],
        ]),
    ]);

    $generator = new KlingVideoGenerator();
    $generator->submitImageToVideo('https://example.com/img.jpg', 'jwt test');

    Http::assertSent(function ($request) {
        $authHeader = $request->header('Authorization')[0] ?? '';

        return str_starts_with($authHeader, 'Bearer ') && strlen($authHeader) > 20;
    });
});

it('throws RuntimeException when access_key is missing', function () {
    config(['services.kling.access_key' => null]);
    new KlingVideoGenerator();
})->throws(RuntimeException::class, 'KLING_ACCESS_KEY is not configured');

it('throws RuntimeException when secret_key is missing', function () {
    config(['services.kling.secret_key' => null]);
    new KlingVideoGenerator();
})->throws(RuntimeException::class, 'KLING_SECRET_KEY is not configured');
