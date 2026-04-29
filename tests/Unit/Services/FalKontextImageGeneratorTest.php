<?php

declare(strict_types=1);

use App\Services\FalKontextImageGenerator;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.fal.key' => 'test-key-123']);
});

it('generates character previews via sync API', function () {
    Http::fake([
        'fal.run/fal-ai/flux-pro/v1.1' => Http::response([
            'images' => [['url' => 'https://fal.ai/output/test.jpg']],
        ]),
    ]);

    $generator = new FalKontextImageGenerator();
    $results = $generator->generateCharacterPreviews('a cute cat', 4);

    expect($results)->toHaveCount(4);
    expect($results[0])->toHaveKeys(['request_id', 'image_url']);
    expect($results[0]['image_url'])->toBe('https://fal.ai/output/test.jpg');
});

it('generates scene image with reference via kontext model', function () {
    Http::fake([
        'fal.run/fal-ai/flux-pro/kontext' => Http::response([
            'images' => [['url' => 'https://fal.ai/output/scene.jpg']],
        ]),
    ]);

    $generator = new FalKontextImageGenerator();
    $result = $generator->generateSceneImage('a park scene', 'https://example.com/char.jpg');

    expect($result['image_url'])->toBe('https://fal.ai/output/scene.jpg');
});

it('throws RuntimeException when API returns error', function () {
    Http::fake([
        'fal.run/fal-ai/flux-pro/kontext' => Http::response(['error' => 'bad request'], 400),
    ]);

    $generator = new FalKontextImageGenerator();
    $generator->generateSceneImage('bad prompt', 'https://example.com/ref.jpg');
})->throws(RuntimeException::class, 'fal.ai request failed');

it('sends correct authorization header', function () {
    Http::fake([
        'fal.run/fal-ai/flux-pro/kontext' => Http::response([
            'images' => [['url' => 'https://fal.ai/output/auth.jpg']],
        ]),
    ]);

    $generator = new FalKontextImageGenerator();
    $generator->generateSceneImage('auth test', 'https://example.com/ref.jpg');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Key test-key-123'));
});
