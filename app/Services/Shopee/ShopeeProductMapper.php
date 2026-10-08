<?php

declare(strict_types=1);

namespace App\Services\Shopee;

use App\Data\Browser\BrowserResult;
use App\Data\ProductData;
use App\Data\ShopeeItemRef;
use App\Exceptions\ShopeeMappingException;
use App\Services\Compliance\TraditionalChineseValidator;

/**
 * get_pc 回應 → ProductData。
 *
 * 設計原則是「蝦皮改版時盡量不要整批失敗」：
 *   1. 全欄位走 data_get() + nullable，缺就留 null
 *   2. 只有 title 缺才算失敗 —— 沒有標題連 LLM 都沒東西可寫，其他欄位 operator 補得回來
 *   3. 原始回應整包存 products.raw_payload，改版後可以回推新 schema 而不用重爬
 *      （重爬才是真正的風險，raw_payload 的儲存成本遠低於一次帳號風控）
 *
 * ⚠️ 價格一律 ÷ config('services.shopee.price_divisor')（100000）。
 *    get_pc 的 price 是整數分母放大值，忘了除會變成 NT$129,000,000。
 */
final class ShopeeProductMapper
{
    public function __construct(private readonly TraditionalChineseValidator $zhTw) {}

    /**
     * 依 BrowserResult 的形狀分流：XHR 攔到的放在 data.raw，DOM 降級的放在 data.dom。
     *
     * @throws ShopeeMappingException
     */
    public function fromBrowserResult(BrowserResult $result, ?ShopeeItemRef $ref = null): ProductData
    {
        if (filled($raw = $result->data['raw'] ?? null)) {
            return $this->fromGetPc((array) $raw, $ref);
        }

        if (filled($dom = $result->data['dom'] ?? null)) {
            return $this->fromDom((array) $dom);
        }

        throw new ShopeeMappingException('瀏覽器沒有回傳可解析的商品資料（data.raw 與 data.dom 都是空的）');
    }

    /**
     * @param  array<string, mixed>  $payload get_pc 的完整回應（或已剝到 data.item 的那層）
     *
     * @throws ShopeeMappingException
     */
    public function fromGetPc(array $payload, ?ShopeeItemRef $ref = null): ProductData
    {
        if (filled($error = data_get($payload, 'error'))) {
            throw new ShopeeMappingException("get_pc 回傳錯誤碼 {$error}（通常代表簽章驗證失敗或商品已下架）");
        }

        // 三種可能的巢狀深度都接受：完整回應 / {item:...} / 已剝好的 item
        $item = (array) (data_get($payload, 'data.item') ?? data_get($payload, 'item') ?? $payload);
        $title = trim((string) data_get($item, 'title', ''));

        if ($title === '') {
            throw new ShopeeMappingException('get_pc 回應缺少 title，無法建立商品（蝦皮可能已改版，請查看 raw_payload）');
        }

        $categories = $this->categories($item);
        $hashes = $this->imageHashes($item);

        return new ProductData(
            title: $title,
            titleHasSimplified: $this->zhTw->findSimplifiedChars($title) !== [],
            brand: $this->nullableString(data_get($item, 'brand')),
            // category 取最末層：「手機平板與周邊 > 耳機」要的是「耳機」
            category: $categories !== [] ? end($categories) : null,
            categoryPath: $categories,
            price: $this->money(data_get($item, 'price')),
            priceMin: $this->money(data_get($item, 'price_min')),
            priceMax: $this->money(data_get($item, 'price_max')),
            priceBeforeDiscount: $this->money(data_get($item, 'price_before_discount')),
            currency: (string) (data_get($item, 'currency') ?: 'TWD'),
            ratingStar: $this->nullableFloat(data_get($item, 'item_rating.rating_star')),
            ratingCount: $this->ratingCount($item),
            // global_sold_count 是跨站總銷量，historical_sold 缺的時候拿它頂著
            historicalSold: $this->nullableInt(data_get($item, 'historical_sold') ?? data_get($item, 'global_sold_count')),
            stock: $this->nullableInt(data_get($item, 'stock')),
            description: $this->nullableString(data_get($item, 'description')),
            variations: $this->variations($item),
            images: array_map(fn (string $hash) => ShopeeLinkParser::imageUrlFromHash($hash), $hashes),
            imageHashes: $hashes,
            rawPayload: $payload,
        );
    }

    /**
     * DOM 降級：只拿得到標題、價格與圖片網址，其他一律 null。
     *
     * @param  array<string, mixed>  $dom
     *
     * @throws ShopeeMappingException
     */
    public function fromDom(array $dom): ProductData
    {
        $title = trim((string) data_get($dom, 'title', ''));

        if ($title === '') {
            throw new ShopeeMappingException('DOM 降級抓取也拿不到標題，請人工填寫商品資料');
        }

        $images = array_values(array_filter(array_map(
            fn ($url) => is_string($url) ? ShopeeLinkParser::originalImageUrl($url) : null,
            (array) data_get($dom, 'images', []),
        )));

        return new ProductData(
            title: $title,
            titleHasSimplified: $this->zhTw->findSimplifiedChars($title) !== [],
            // DOM 的價格是從「$1,290」解析出來的新台幣，不再除 price_divisor
            price: $this->nullableFloat(data_get($dom, 'price')),
            description: $this->nullableString(data_get($dom, 'description')),
            images: $images,
            imageHashes: array_map(fn (string $url) => basename((string) parse_url($url, PHP_URL_PATH)), $images),
            rawPayload: ['dom' => $dom],
        );
    }

    /** get_pc 的價格是 ×100000 的整數 */
    private function money(mixed $value): ?float
    {
        if (! is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        return round((float) $value / max(1, (int) config('services.shopee.price_divisor', 100000)), 2);
    }

    /** @return list<string> */
    private function categories(array $item): array
    {
        return array_values(array_filter(array_map(
            fn ($c) => $this->nullableString(data_get($c, 'display_name') ?? data_get($c, 'name')),
            (array) data_get($item, 'categories', []),
        )));
    }

    /** @return list<string> */
    private function imageHashes(array $item): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($hash) => is_string($hash) && trim($hash) !== '' ? trim($hash) : null,
            (array) data_get($item, 'images', []),
        ))));
    }

    /** @return list<array{name: string, options: list<string>}> */
    private function variations(array $item): array
    {
        $tiers = [];

        foreach ((array) data_get($item, 'tier_variations', []) as $tier) {
            $name = $this->nullableString(data_get($tier, 'name'));
            $options = array_values(array_filter(array_map(
                fn ($o) => $this->nullableString(is_array($o) ? data_get($o, 'option') : $o),
                (array) data_get($tier, 'options', []),
            )));

            if ($name !== null || $options !== []) {
                $tiers[] = ['name' => (string) $name, 'options' => $options];
            }
        }

        return $tiers;
    }

    /** rating_count 可能是純數字，也可能是 [總數, 1星, 2星…] 的分佈陣列 */
    private function ratingCount(array $item): ?int
    {
        $value = data_get($item, 'item_rating.rating_count') ?? data_get($item, 'rating_count');

        return $this->nullableInt(is_array($value) ? ($value[0] ?? null) : $value);
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
