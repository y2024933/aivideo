<?php

declare(strict_types=1);

use App\Exceptions\InvalidShopeeLinkException;
use App\Services\Shopee\ShopeeLinkParser;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->parser = new ShopeeLinkParser();
});

// ⚠️ -i.{A}.{B} 與 /product/{A}/{B} 的 A 都是 shopId，B 才是 itemId
it('parses the 6 supported shopee link formats', function (string $url, array $redirects = []) {
    if ($redirects !== []) {
        Http::fake($redirects);
    }

    $ref = $this->parser->parse($url);

    expect($ref->shopId)->toBe(111)
        ->and($ref->itemId)->toBe(222)
        ->and($ref->canonicalUrl)->toBe('https://shopee.tw/product/111/222');
})->with([
    'item slug' => ['https://shopee.tw/無線藍牙耳機-ANC降噪-i.111.222'],
    'item slug with tracking params' => ['https://shopee.tw/無線藍牙耳機-ANC降噪-i.111.222?sp_atk=abc-123&xptdk=def-456'],
    'product path' => ['https://shopee.tw/product/111/222'],
    'universal link' => ['https://shopee.tw/universal-link/product/111/222'],
    's.shopee.tw short link' => [
        'https://s.shopee.tw/AbCdEf123',
        ['s.shopee.tw/*' => fn () => Http::response('', 200, ['X-Guzzle-Redirect-History' => 'https://shopee.tw/無線藍牙耳機-i.111.222'])],
    ],
    'shope.ee short link' => [
        'https://shope.ee/xxxxx',
        ['shope.ee/*' => fn () => Http::response('', 200, ['X-Guzzle-Redirect-History' => 'https://shopee.tw/product/111/222'])],
    ],
]);

it('keeps shopId before itemId (not the intuitive order)', function () {
    $ref = $this->parser->parse('https://shopee.tw/x-i.123456789.987654321');

    expect($ref->shopId)->toBe(123456789)
        ->and($ref->itemId)->toBe(987654321)
        ->and($ref->apiQuery())->toBe(['shop_id' => 123456789, 'item_id' => 987654321]);
});

it('throws when an expired short link lands on error_page', function () {
    Http::fake(['s.shopee.tw/*' => fn () => Http::response('', 200, ['X-Guzzle-Redirect-History' => 'https://shope.ee/error_page'])]);

    expect(fn () => $this->parser->parse('https://s.shopee.tw/deadcode'))
        ->toThrow(InvalidShopeeLinkException::class, '連結無效');
});

it('throws on links without item ids', function (string $url) {
    expect(fn () => $this->parser->parse($url))->toThrow(InvalidShopeeLinkException::class);
})->with([
    ['https://shopee.tw/'],
    ['https://shopee.tw/search?keyword=耳機'],
    ['https://example.com/product/111/222'],
    // 仿冒網域不可被當成蝦皮
    ['https://evil-shopee.com/x-i.111.222'],
    ['https://shopee.tw.attacker.net/x-i.111.222'],
    ['not a url at all'],
    ['ftp://shopee.tw/x-i.111.222'],
]);

it('strips resize suffixes to get the original image', function (string $input, string $expected) {
    expect(ShopeeLinkParser::originalImageUrl($input))->toBe($expected);
})->with([
    ['https://down-tw.img.susercontent.com/file/abc123@resize_w900_nl.webp', 'https://down-tw.img.susercontent.com/file/abc123'],
    ['https://down-tw.img.susercontent.com/file/abc123@resize_w450', 'https://down-tw.img.susercontent.com/file/abc123'],
    ['https://down-tw.img.susercontent.com/file/abc123', 'https://down-tw.img.susercontent.com/file/abc123'],
]);

it('builds image urls from the cdn prefix and hash', function () {
    config(['services.shopee.image_cdn' => 'https://down-tw.img.susercontent.com/file/']);

    expect(ShopeeLinkParser::imageUrlFromHash('abc123'))->toBe('https://down-tw.img.susercontent.com/file/abc123');
});
