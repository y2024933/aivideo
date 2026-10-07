<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Enums\ProductStatus;
use App\Exceptions\InvalidShopeeLinkException;
use App\Filament\Resources\ProductResource;
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
     * P1 只建骨架：抓資料是 P5 的 Playwright 任務，這裡驗證連結合法後導去 Edit 頁人工補齊。
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

        Notification::make()->success()->title('已建立商品骨架')->body('請補齊標題、價格與圖片後送審。')->send();
        $this->redirect(ProductResource::getUrl('edit', ['record' => $product]));
    }
}
