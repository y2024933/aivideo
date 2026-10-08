<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * 映射完成的商品資料（來源可能是 get_pc 或 DOM 降級）。
 *
 * 這一層存在的意義是「蝦皮改版時只壞一個檔案」：Job 與 Filament 都只認這個形狀，
 * get_pc 的欄位怎麼搬家都關在 ShopeeProductMapper 裡。
 */
final class ProductData extends Data
{
    /**
     * @param  list<array{name: string, options: list<string>}>  $variations
     * @param  list<string>  $categoryPath
     * @param  list<string>  $images 原圖絕對網址
     * @param  list<string>  $imageHashes
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $title,
        public bool $titleHasSimplified = false,
        public ?string $brand = null,
        public ?string $category = null,
        public array $categoryPath = [],
        public ?float $price = null,
        public ?float $priceMin = null,
        public ?float $priceMax = null,
        public ?float $priceBeforeDiscount = null,
        public string $currency = 'TWD',
        public ?float $ratingStar = null,
        public ?int $ratingCount = null,
        public ?int $historicalSold = null,
        public ?int $stock = null,
        public ?string $description = null,
        public array $variations = [],
        public array $images = [],
        public array $imageHashes = [],
        public array $rawPayload = [],
    ) {}

    /**
     * 可直接丟給 Product::update() 的欄位。
     * 刻意不含 status／source：狀態轉移一律走 transitionTo()。
     *
     * @return array<string, mixed>
     */
    public function toProductAttributes(): array
    {
        return [
            'title' => mb_substr($this->title, 0, 512),
            'title_has_simplified' => $this->titleHasSimplified,
            'brand' => $this->brand,
            'category' => $this->category,
            'category_path' => $this->categoryPath,
            'price' => $this->price,
            'price_min' => $this->priceMin,
            'price_max' => $this->priceMax,
            'price_before_discount' => $this->priceBeforeDiscount,
            'currency' => $this->currency,
            'rating_star' => $this->ratingStar,
            'rating_count' => $this->ratingCount,
            'historical_sold' => $this->historicalSold,
            'stock' => $this->stock,
            'description' => $this->description,
            'variations' => $this->variations,
            'raw_payload' => $this->rawPayload,
            'scraped_at' => now(),
        ];
    }
}
