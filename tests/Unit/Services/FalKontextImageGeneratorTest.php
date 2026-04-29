<?php

declare(strict_types=1);

use App\Services\FalKontextImageGenerator;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.fal.key' => 'test-key-123']);
});

it('generates character previews via queue submit + poll + result', function () {
    Http::fake([
        // Submit (4 次)
        'queue.fal.run/fal-ai/flux-pro/v1.1' => Http::sequence()
            ->push(['request_id' => 'req_1'], 200)
            ->push(['request_id' => 'req_2'], 200)
            ->push(['request_id' => 'req_3'], 200)
            ->push(['request_id' => 'req_4'], 200),

        // Poll status (4 次，每次直接 COMPLETED)
        'queue.fal.run/fal-ai/flux-pro/v1.1/requests/*/status' => Http::response(['status' => 'COMPLETED']),

        // Get result (4 次)
        'queue.fal.run/fal-ai/flux-pro/v1.1/requests/*' => Http::response([
            'images' => [['url' => 'https://fal.ai/output/test.jpg']],
        ]),
    ]);

    $generator = new FalKontextImageGenerator(pollIntervalSeconds: 0);
    $results = $generator->generateCharacterPreviews('a cute cat', 4);

    expect($results)->toHaveCount(4);
    expect($results[0])->toHaveKeys(['request_id', 'image_url']);
    expect($results[0]['image_url'])->toBe('https://fal.ai/output/test.jpg');
});

it('generates scene image with reference via kontext model', function () {
    Http::fake([
        'queue.fal.run/fal-ai/flux-pro/kontext' => Http::response(['request_id' => 'req_scene_1'], 200),
        'queue.fal.run/fal-ai/flux-pro/kontext/requests/*/status' => Http::response(['status' => 'COMPLETED']),
        'queue.fal.run/fal-ai/flux-pro/kontext/requests/*' => Http::response([
            'images' => [['url' => 'https://fal.ai/output/scene.jpg']],
        ]),
    ]);

    $generator = new FalKontextImageGenerator(pollIntervalSeconds: 0);
    $result = $generator->generateSceneImage('a park scene', 'https://example.com/char.jpg');

    expect($result['image_url'])->toBe('https://fal.ai/output/scene.jpg');
    expect($result['request_id'])->toBe('req_scene_1');
});

it('throws RuntimeException when task fails', function () {
    Http::fake([
        'queue.fal.run/fal-ai/flux-pro/kontext' => Http::response(['request_id' => 'req_fail'], 200),
        'queue.fal.run/fal-ai/flux-pro/kontext/requests/*/status' => Http::response(['status' => 'FAILED']),
    ]);

    $generator = new FalKontextImageGenerator(pollIntervalSeconds: 0);
    $generator->generateSceneImage('fail prompt', 'https://example.com/ref.jpg');
})->throws(RuntimeException::class, 'fal.ai task FAILED');

it('throws RuntimeException when submit returns error', function () {
    Http::fake([
        'queue.fal.run/fal-ai/flux-pro/kontext' => Http::response(['error' => 'bad request'], 400),
    ]);

    $generator = new FalKontextImageGenerator(pollIntervalSeconds: 0);
    $generator->generateSceneImage('bad prompt', 'https://example.com/ref.jpg');
})->throws(RuntimeException::class, 'fal.ai submit failed');

it('sends correct authorization header', function () {
    Http::fake([
        'queue.fal.run/fal-ai/flux-pro/kontext' => Http::response(['request_id' => 'req_auth'], 200),
        'queue.fal.run/fal-ai/flux-pro/kontext/requests/*/status' => Http::response(['status' => 'COMPLETED']),
        'queue.fal.run/fal-ai/flux-pro/kontext/requests/*' => Http::response([
            'images' => [['url' => 'https://fal.ai/output/auth.jpg']],
        ]),
    ]);

    $generator = new FalKontextImageGenerator(pollIntervalSeconds: 0);
    $generator->generateSceneImage('auth test', 'https://example.com/ref.jpg');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Key test-key-123'));
});
