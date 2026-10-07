<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * 蝦皮商品識別。注意 shopId 在連結與 API 中都排在 itemId 前面。
 */
final class ShopeeItemRef extends Data
{
    public function __construct(
        public int $shopId,
        public int $itemId,
        public string $canonicalUrl,
    ) {}

    /** get_pc API 的 query string（shop_id 在前） */
    public function apiQuery(): array
    {
        return ['shop_id' => $this->shopId, 'item_id' => $this->itemId];
    }
}
