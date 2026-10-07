<?php

declare(strict_types=1);

namespace App\Services\Shopee;

use App\Data\ShopeeItemRef;
use App\Exceptions\InvalidShopeeLinkException;
use Illuminate\Support\Facades\Http;

/**
 * 蝦皮商品連結解析（純 HTTP，不需要瀏覽器）。
 *
 * 分潤短連結走的是標準 HTTP 301，不是 JS 跳轉，所以用 Guzzle 跟著 redirect 就能還原。
 */
final class ShopeeLinkParser
{
    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    private const SITE_HOST = 'shopee.tw';

    /** 分潤／行銷短網域，需先跟 redirect 才拿得到商品 id */
    private const SHORT_HOSTS = ['s.shopee.tw', 'shope.ee', 'shp.ee'];

    /**
     * @throws InvalidShopeeLinkException
     */
    public function parse(string $url): ShopeeItemRef
    {
        $url = trim($url);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        // 不用 FILTER_VALIDATE_URL：商品 slug 含中文（非 ASCII）會被它判為無效
        if (! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) || $host === '') {
            throw new InvalidShopeeLinkException('不是有效的網址');
        }

        // 白名單比對而非 str_contains，否則 evil-shopee.com / shopee.tw.attacker.net 都會被放行
        if (! in_array($host, self::SHORT_HOSTS, true) && $host !== self::SITE_HOST && ! str_ends_with($host, '.' . self::SITE_HOST)) {
            throw new InvalidShopeeLinkException("不是蝦皮網域：{$host}");
        }

        if (in_array($host, self::SHORT_HOSTS, true)) {
            $url = $this->resolveShortLink($url);
        }

        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));

        // ⚠️ 兩種格式的第一個數字都是 shopId、第二個才是 itemId（與直覺相反）
        if (! preg_match('#-i\.(\d+)\.(\d+)#', $path, $m) && ! preg_match('#/product/(\d+)/(\d+)#', $path, $m)) {
            throw new InvalidShopeeLinkException('無法從連結取得商品編號，請確認是蝦皮商品頁連結');
        }

        return new ShopeeItemRef((int) $m[1], (int) $m[2], "https://shopee.tw/product/{$m[1]}/{$m[2]}");
    }

    /**
     * 跟隨 301 取得真實商品網址
     *
     * @throws InvalidShopeeLinkException
     */
    public function resolveShortLink(string $url): string
    {
        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->withOptions(['allow_redirects' => ['max' => 5, 'track_redirects' => true]])
            ->timeout(15)
            ->get($url);

        if ($response->failed()) {
            throw new InvalidShopeeLinkException("短連結解析失敗：HTTP {$response->status()}");
        }

        // track_redirects 會把跳轉鏈寫進 X-Guzzle-Redirect-History，最後一筆即落地網址；
        // 沒有跳轉時退回 effectiveUri()（它依賴 transferStats，Http::fake 下取不到）
        $history = array_filter(array_map('trim', explode(',', (string) $response->header('X-Guzzle-Redirect-History'))));
        $effective = $history !== [] ? (string) end($history) : (string) $response->effectiveUri();

        // 失效的 code 會被導去 error_page 且回 HTTP 200，必須用網址判斷而非狀態碼
        if ($effective === '' || str_contains($effective, 'error_page')) {
            throw new InvalidShopeeLinkException('連結無效');
        }

        return $effective;
    }

    /** 去掉 @resize_w900_nl.webp 這類後綴，拿未壓縮原圖 */
    public static function originalImageUrl(string $url): string
    {
        return preg_replace('/@resize_w\d+(_nl)?(\.\w+)?$/', '', $url) ?? $url;
    }

    /** 蝦皮圖片只存 hash，實際網址 = CDN 前綴 + hash */
    public static function imageUrlFromHash(string $hash): string
    {
        return rtrim((string) config('services.shopee.image_cdn'), '/') . '/' . ltrim($hash, '/');
    }
}
