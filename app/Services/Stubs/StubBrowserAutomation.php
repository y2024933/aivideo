<?php

declare(strict_types=1);

namespace App\Services\Stubs;

use App\Data\Browser\BrowserResult;
use App\Data\ShopeeItemRef;
use App\Enums\BrowserStrategy;
use App\Enums\BrowserTaskType;
use App\Models\BrowserTask;
use App\Services\Browser\BrowserTaskRecorder;
use App\Services\Contracts\BrowserAutomationContract;

/**
 * 零網路的瀏覽器替身。
 *
 * ⚠️ 這是整個專案最重要的一顆 stub。其他 stub 省的是錢（付費 API），這顆省的是帳號 ——
 * 蝦皮封號只能靠人工 SMS OTP 恢復，沒有任何程式化的補救方式。所以：
 *   - 測試一律綁這顆，絕不綁 PlaywrightBrowserAutomation
 *   - 它不發任何 HTTP 請求、不開瀏覽器，連 fixture 都是手寫假資料
 *
 * 四種情境都要能被注入，否則 Job 的降級／失敗／人工分支永遠沒測過：
 *   (new StubBrowserAutomation)->respondWith(BrowserResult::DEGRADED)
 */
final class StubBrowserAutomation implements BrowserAutomationContract
{
    /** @var list<array{method: string, args: array<string, mixed>}> */
    public array $calls = [];

    private ?BrowserTask $lastTask = null;

    /** @param array<string, mixed>|null $data null = 用內建 fixture */
    public function __construct(
        private string $status = BrowserResult::SUCCEEDED,
        private ?string $strategy = null,
        private ?array $data = null,
        private string $errorCode = 'stub_failure',
        private string $errorMessage = '（stub）模擬的瀏覽器失敗',
    ) {}

    /** @param array<string, mixed>|null $data */
    public function respondWith(string $status, ?array $data = null, ?string $strategy = null, ?string $errorCode = null, ?string $errorMessage = null): self
    {
        $this->status = $status;
        $this->data = $data ?? $this->data;
        $this->strategy = $strategy;
        $this->errorCode = $errorCode ?? $this->errorCode;
        $this->errorMessage = $errorMessage ?? $this->errorMessage;

        return $this;
    }

    public function scrapeShopeeProduct(ShopeeItemRef $ref, array $options = []): BrowserResult
    {
        $this->calls[] = ['method' => 'scrapeShopeeProduct', 'args' => ['shop_id' => $ref->shopId, 'item_id' => $ref->itemId]];

        $data = $this->data ?? ($this->status === BrowserResult::DEGRADED ? self::shopeeDomFixture($ref) : self::shopeeFixture($ref));

        return $this->record(BrowserTaskType::ShopeeScrapeProduct, $ref->apiQuery(), $options, $data);
    }

    public function fetchImagesFromUrl(string $url, array $options = []): BrowserResult
    {
        $this->calls[] = ['method' => 'fetchImagesFromUrl', 'args' => ['url' => $url]];

        return $this->record(BrowserTaskType::ExternalImageScrape, ['url' => $url], $options, $this->data ?? [
            'images' => ['https://placehold.co/1000x1000.png', 'https://placehold.co/1080x1920.png'],
        ]);
    }

    public function checkSessions(array $profiles = []): BrowserResult
    {
        $this->calls[] = ['method' => 'checkSessions', 'args' => ['profiles' => $profiles]];
        $profiles = $profiles !== [] ? $profiles : ['shopee-seller'];

        return $this->record(BrowserTaskType::ShopeeSessionCheck, ['profiles' => $profiles], [], $this->data ?? [
            'sessions' => array_map(fn (string $p) => ['profile' => $p, 'loggedIn' => true], array_values($profiles)),
        ]);
    }

    public function lastTask(): ?BrowserTask
    {
        return $this->lastTask;
    }

    /**
     * get_pc 的「形狀」假資料。
     *
     * ⚠️ 全部手寫，不是真實抓取結果 —— 連 fixture 都用真資料的話，等於有人為了
     * 更新 fixture 就得去抓一次蝦皮。價格刻意用 ×100000 的原始單位，才測得出除法。
     *
     * @return array<string, mixed>
     */
    public static function shopeeFixture(?ShopeeItemRef $ref = null): array
    {
        return [
            'raw' => [
                'data' => [
                    'item' => [
                        'shop_id' => $ref?->shopId ?? 111,
                        'item_id' => $ref?->itemId ?? 222,
                        'title' => '無線藍牙耳機 ANC主動降噪 續航32小時',
                        'description' => "主動降噪、單次續航 8 小時。\n附充電盒總續航 32 小時。",
                        'brand' => 'SoundCore',
                        'price' => 129000000,
                        'price_min' => 129000000,
                        'price_max' => 159000000,
                        'price_before_discount' => 199000000,
                        'currency' => 'TWD',
                        'stock' => 42,
                        'historical_sold' => 5678,
                        'item_rating' => ['rating_star' => 4.83, 'rating_count' => [1234, 5, 10, 40, 300, 879]],
                        'images' => ['stubhash0000000000000000000000a1', 'stubhash0000000000000000000000a2', 'stubhash0000000000000000000000a3'],
                        'tier_variations' => [['name' => '顏色', 'options' => ['曜石黑', '雲霧白']]],
                        'categories' => [
                            ['catid' => 1, 'display_name' => '手機平板與周邊'],
                            ['catid' => 2, 'display_name' => '耳機'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 降級路徑的形狀：DOM 撈出來的價格已經是新台幣（從「$1,290」解析），不再 ×100000，
     * 而且沒有評分、銷量、規格 —— 這正是 degraded 不能自動放行 checkpoint ① 的原因。
     *
     * @return array<string, mixed>
     */
    public static function shopeeDomFixture(?ShopeeItemRef $ref = null): array
    {
        return [
            'dom' => [
                'title' => '無線藍牙耳機 ANC主動降噪 續航32小時',
                'price' => 1290.0,
                'images' => [
                    'https://down-tw.img.susercontent.com/file/stubhash0000000000000000000000a1',
                    'https://down-tw.img.susercontent.com/file/stubhash0000000000000000000000a2',
                ],
                'url' => $ref?->canonicalUrl,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $data
     */
    private function record(BrowserTaskType $type, array $payload, array $options, array $data): BrowserResult
    {
        $recorder = app(BrowserTaskRecorder::class);
        $this->lastTask = $recorder->start($type, $payload, [...$options, 'max_attempts' => 1]);
        $this->lastTask->markRunning();

        $result = match ($this->status) {
            BrowserResult::SUCCEEDED => BrowserResult::ok($data, $this->strategy ?? BrowserStrategy::Xhr->value, 1200),
            BrowserResult::DEGRADED => BrowserResult::degrade($data, $this->errorCode, $this->errorMessage, 2400),
            BrowserResult::NEEDS_MANUAL => BrowserResult::manual($this->errorCode, $this->errorMessage, 3600),
            default => BrowserResult::fail($this->errorCode, $this->errorMessage, 800),
        };

        $recorder->finish($this->lastTask, $result);

        return $result;
    }
}
