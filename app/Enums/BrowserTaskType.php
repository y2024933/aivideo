<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BrowserTaskType: string implements HasLabel
{
    case ShopeeScrapeProduct = 'shopee_scrape_product';
    case ResolveShortLink = 'resolve_short_link';
    case ExternalImageScrape = 'external_image_scrape';
    case ShopeeSessionCheck = 'shopee_session_check';
    case ShopeePublishDraft = 'shopee_publish_draft';
    case DolaGenerateVideo = 'dola_generate_video';

    public function getLabel(): string
    {
        return match ($this) {
            self::ShopeeScrapeProduct => '蝦皮商品抓取',
            self::ResolveShortLink => '短連結還原',
            self::ExternalImageScrape => '外站圖片抓取',
            self::ShopeeSessionCheck => '蝦皮登入狀態檢查',
            self::ShopeePublishDraft => '蝦皮草稿填表',
            self::DolaGenerateVideo => 'Dola 影片生成',
        };
    }
}
