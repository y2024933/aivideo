<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Data\Browser\BrowserResult;
use App\Data\ShopeeItemRef;

/**
 * 瀏覽器自動化。實作一律不丟例外，失敗用 BrowserResult 的 status 表達 ——
 * 呼叫端幾乎都需要「失敗也要寫進 browser_tasks 並給 operator 看原因」，
 * 用例外表達會讓每個呼叫點都得包 try/catch。
 */
interface BrowserAutomationContract
{
    /**
     * 抓蝦皮商品。
     *
     * ⚠️ 必須用「未登入」的 profile：商品頁不需登入就看得到，用登入 context 等於
     * 拿帳號去換零價值的資料，被風控就只能人工 SMS OTP 重登。
     *
     * @param  array{subject?: \Illuminate\Database\Eloquent\Model, allow_dom_fallback?: bool, profile?: string}  $options
     */
    public function scrapeShopeeProduct(ShopeeItemRef $ref, array $options = []): BrowserResult;

    /**
     * 從任意網頁撈圖片網址（外站素材用）。
     *
     * @param  array{subject?: \Illuminate\Database\Eloquent\Model, min_width?: int, limit?: int}  $options
     */
    public function fetchImagesFromUrl(string $url, array $options = []): BrowserResult;

    /**
     * 檢查登入 profile 還活著沒（上架前的前置檢查，不做任何寫入動作）。
     *
     * @param  list<string>  $profiles
     */
    public function checkSessions(array $profiles = []): BrowserResult;
}
