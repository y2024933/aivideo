<?php

declare(strict_types=1);

use App\Data\Browser\BrowserResult;
use App\Data\ShopeeItemRef;
use App\Exceptions\ShopeeMappingException;
use App\Services\Shopee\ShopeeProductMapper;

/**
 * get_pc → ProductData 的映射。
 *
 * ⚠️ fixture 是手寫假資料（tests/Fixtures/shopee/get_pc_sample.json），不是真實抓取結果。
 *    「為了更新 fixture 去抓一次蝦皮」是這個專案最不該發生的事 —— 封的是帳號。
 */
beforeEach(function () {
    config([
        'services.shopee.price_divisor' => 100000,
        // 刻意用測試網域：真實蝦皮 CDN 連「出現在測試檔裡」都不需要
        // （NoRealApiGuardTest 會把裸露的真實網域當成潛在的對外連線擋下來）
        'services.shopee.image_cdn' => 'https://cdn.example.test/file/',
    ]);

    $this->mapper = app(ShopeeProductMapper::class);
    $this->payload = json_decode((string) file_get_contents(base_path('tests/Fixtures/shopee/get_pc_sample.json')), true);
});

it('divides every price field by the price divisor', function () {
    $data = $this->mapper->fromGetPc($this->payload);

    // ⚠️ 129000000 / 100000 = 1290.00。忘了除會變成 NT$129,000,000
    expect($data->price)->toBe(1290.0)
        ->and($data->priceMin)->toBe(1290.0)
        ->and($data->priceMax)->toBe(1590.0)
        ->and($data->priceBeforeDiscount)->toBe(1990.0)
        ->and($data->currency)->toBe('TWD');
});

it('reads the divisor from config instead of hard coding it', function () {
    config(['services.shopee.price_divisor' => 1000]);

    expect($this->mapper->fromGetPc($this->payload)->price)->toBe(129000.0);
});

it('builds cdn urls from image hashes', function () {
    $data = $this->mapper->fromGetPc($this->payload);

    expect($data->imageHashes)->toHaveCount(3)
        ->and($data->images[0])->toBe('https://cdn.example.test/file/tw-11134207-7r98o-fixturehash000001')
        ->and($data->images)->toHaveCount(3);
});

it('flattens categories into a path and takes the leaf as category', function () {
    $data = $this->mapper->fromGetPc($this->payload);

    expect($data->categoryPath)->toBe(['手機平板與周邊', '耳機與喇叭', '藍牙耳機'])
        ->and($data->category)->toBe('藍牙耳機');
});

it('maps rating, sold count, stock and tier variations', function () {
    $data = $this->mapper->fromGetPc($this->payload);

    expect($data->ratingStar)->toBe(4.83)
        // rating_count 是 [總數, 1星, 2星…] 的分佈陣列，要取第 0 個
        ->and($data->ratingCount)->toBe(1234)
        ->and($data->historicalSold)->toBe(5678)
        ->and($data->stock)->toBe(42)
        ->and($data->brand)->toBe('SoundCore')
        ->and($data->variations)->toBe([
            ['name' => '顏色', 'options' => ['曜石黑', '雲霧白']],
            ['name' => '組合', 'options' => ['單耳機', '耳機+收納包']],
        ]);
});

it('accepts a scalar rating_count as well as the distribution array', function () {
    data_set($this->payload, 'data.item.item_rating.rating_count', 987);

    expect($this->mapper->fromGetPc($this->payload)->ratingCount)->toBe(987);
});

it('falls back to global_sold_count when historical_sold is missing', function () {
    data_set($this->payload, 'data.item.historical_sold', null);

    expect($this->mapper->fromGetPc($this->payload)->historicalSold)->toBe(6100);
});

it('flags simplified chinese in the title', function () {
    data_set($this->payload, 'data.item.title', '无线蓝牙耳机 主动降噪');

    $data = $this->mapper->fromGetPc($this->payload);

    expect($data->titleHasSimplified)->toBeTrue();
});

it('does not flag a clean traditional title', function () {
    expect($this->mapper->fromGetPc($this->payload)->titleHasSimplified)->toBeFalse();
});

it('keeps the whole raw payload so a shopee redesign can be reverse engineered', function () {
    // 重爬才是真正的風險，raw_payload 的儲存成本遠低於一次帳號風控
    $data = $this->mapper->fromGetPc($this->payload);

    expect($data->rawPayload)->toBe($this->payload)
        ->and($data->toProductAttributes()['raw_payload'])->toBe($this->payload);
});

it('only fails when the title is missing', function (string $path) {
    data_set($this->payload, "data.item.{$path}", null);

    $data = $this->mapper->fromGetPc($this->payload);

    // 其他欄位缺失要能容忍：operator 補得回來，整批匯入失敗補不回來
    expect($data->title)->toBe('無線藍牙耳機 ANC主動降噪 續航32小時');
})->with([
    'price', 'price_min', 'price_max', 'price_before_discount', 'brand', 'currency',
    'stock', 'description', 'item_rating', 'images', 'tier_variations', 'categories',
    'historical_sold',
]);

it('tolerates an item stripped down to just a title', function () {
    $data = $this->mapper->fromGetPc(['data' => ['item' => ['title' => '只剩標題的商品']]]);

    expect($data->title)->toBe('只剩標題的商品')
        ->and($data->price)->toBeNull()
        ->and($data->images)->toBe([])
        ->and($data->categoryPath)->toBe([])
        ->and($data->variations)->toBe([]);
});

it('throws when the title is missing', function () {
    data_set($this->payload, 'data.item.title', null);

    expect(fn () => $this->mapper->fromGetPc($this->payload))
        ->toThrow(ShopeeMappingException::class, '缺少 title');
});

it('throws when shopee returns an error code', function () {
    // 90309999 = 簽章驗證失敗，就是純 HTTP 直打會拿到的那個
    expect(fn () => $this->mapper->fromGetPc(['error' => 90309999, 'data' => null]))
        ->toThrow(ShopeeMappingException::class, '90309999');
});

it('accepts payloads already stripped to the item level', function () {
    $item = data_get($this->payload, 'data.item');

    expect($this->mapper->fromGetPc($item)->price)->toBe(1290.0)
        ->and($this->mapper->fromGetPc(['item' => $item])->price)->toBe(1290.0);
});

it('maps the dom fallback shape without dividing the price', function () {
    // DOM 的價格是從「$1,290」解析出來的新台幣，再除 100000 會變成 0.01
    $data = $this->mapper->fromDom([
        'title' => '無線藍牙耳機',
        'price' => 1290.0,
        'images' => ['https://cdn.example.test/file/abc123@resize_w900_nl.webp'],
    ]);

    expect($data->price)->toBe(1290.0)
        ->and($data->images)->toBe(['https://cdn.example.test/file/abc123'])
        ->and($data->imageHashes)->toBe(['abc123'])
        ->and($data->ratingStar)->toBeNull();
});

it('dispatches on the browser result shape', function () {
    // canonicalUrl 在映射階段只是帶著走，不會被請求；仍用測試網域以免這個檔案被
    // NoRealApiGuardTest 當成潛在的對外連線
    $ref = new ShopeeItemRef(111, 222, 'https://shop.example.test/product/111/222');

    expect($this->mapper->fromBrowserResult(BrowserResult::ok(['raw' => $this->payload]), $ref)->price)->toBe(1290.0)
        ->and($this->mapper->fromBrowserResult(BrowserResult::degrade(['dom' => ['title' => 'DOM 標題', 'price' => 99.0]]), $ref)->title)->toBe('DOM 標題');
});

it('throws when the browser returned neither raw nor dom data', function () {
    expect(fn () => $this->mapper->fromBrowserResult(BrowserResult::ok([])))
        ->toThrow(ShopeeMappingException::class);
});
