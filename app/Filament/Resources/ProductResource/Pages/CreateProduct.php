<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Enums\ProductStatus;
use App\Exceptions\InvalidShopeeLinkException;
use App\Filament\Resources\ProductResource;
use App\Jobs\ScrapeShopeeProductJob;
use App\Models\Product;
use App\Services\Shopee\ShopeeLinkParser;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

final class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['subtitle_settings'] ??= config('video.subtitle_defaults');

        return ProductResource::adoptBgmUpload($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('createFromShopeeLink')
                ->label('貼蝦皮連結建立')
                ->icon('heroicon-o-link')
                ->modalSubmitActionLabel('建立')
                ->form([
                    Forms\Components\TextInput::make('url')->label('蝦皮商品連結')->required()
                        ->helperText('支援商品頁網址、/product/ 網址與 s.shopee.tw / shope.ee 分潤短連結。'),
                ])
                ->action(fn (array $data) => $this->createFromShopeeLink($data['url'])),
        ];
    }

    /**
     * 建骨架後派 ScrapeShopeeProductJob 去抓資料。
     *
     * 這裡刻意只建「骨架 + 派工」而不同步抓：一次抓取含瀏覽器啟動、攔 XHR 與內部
     * backoff，最壞要兩三分鐘，撐在 HTTP request 裡一定逾時。抓完的狀態變化由
     * status-poller 推給前端。
     */
    private function createFromShopeeLink(string $url): void
    {
        try {
            $ref = app(ShopeeLinkParser::class)->parse($url);
        } catch (InvalidShopeeLinkException $e) {
            Notification::make()->danger()->title('連結無法解析')->body($e->getMessage())->send();

            return;
        }

        if ($existing = Product::where(['shopee_shop_id' => $ref->shopId, 'shopee_item_id' => $ref->itemId])->first()) {
            Notification::make()->warning()->title('商品已存在')->body('已導向既有紀錄，未重複建立。')->send();
            $this->redirect(ProductResource::getUrl('edit', ['record' => $existing]));

            return;
        }

        $product = Product::create([
            'source' => 'shopee_link',
            'source_url' => $ref->canonicalUrl,
            'affiliate_url' => $url,
            'shopee_shop_id' => $ref->shopId,
            'shopee_item_id' => $ref->itemId,
            'title' => "蝦皮商品 {$ref->itemId}（待補資料）",
            'status' => ProductStatus::Draft,
            'subtitle_settings' => config('video.subtitle_defaults'),
            'disclosure_prefix' => config('compliance.disclosure_prefix'),
        ]);

        ScrapeShopeeProductJob::dispatch($product->id);

        Notification::make()->success()->title('已建立商品，開始抓取資料')
            ->body('抓取含瀏覽器操作，約需 1–3 分鐘。完成後狀態會變成「① 商品資料待確認」；若顯示降級抓取請逐欄核對。')
            ->send();
        $this->redirect(ProductResource::getUrl('edit', ['record' => $product]));
    }
}
