<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'source' => 'manual',
            'title' => '無線藍牙耳機 降噪 長效續航',
            'brand' => 'SoundCore',
            'category' => '3C／耳機',
            'compliance_profile' => '3c',
            'price' => 1290,
            'price_before_discount' => 1990,
            'currency' => 'TWD',
            'rating_star' => 4.8,
            'rating_count' => 1234,
            'historical_sold' => 5678,
            'description' => '主動降噪、單次續航 8 小時，附充電盒總續航 32 小時。',
            'status' => ProductStatus::Draft,
            'video_length_seconds' => 30,
            'audio_mode' => 'none',
            'video_provider' => 'none',
            'global_transition' => 'crossfade',
            'default_ken_burns' => 'auto',
            'subtitle_settings' => config('video.subtitle_defaults'),
            'disclosure_prefix' => config('compliance.disclosure_prefix'),
            'affiliate_url' => 'https://s.shopee.tw/AbCdEf123',
        ];
    }

    public function shopee(int $shopId = 111, int $itemId = 222): self
    {
        return $this->state(fn () => [
            'source' => 'shopee_link',
            'shopee_shop_id' => $shopId,
            'shopee_item_id' => $itemId,
            'source_url' => "https://shopee.tw/product/{$shopId}/{$itemId}",
        ]);
    }

    public function status(ProductStatus $status): self
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function withTts(): self
    {
        return $this->state(fn () => ['audio_mode' => 'tts', 'voice_id_preferred' => 'zh-TW-HsiaoChenNeural']);
    }

    /**
     * 可直接拿去渲染的商品：N 張選用圖 + N 個已綁圖的鏡頭
     */
    public function renderable(int $shots = 5): self
    {
        return $this->afterCreating(function (Product $product) use ($shots) {
            collect(range(1, $shots))->each(function (int $i) use ($product) {
                $image = ProductImage::factory()->for($product)->create(['sort_order' => $i, 'is_primary' => $i === 1]);

                Shot::factory()->for($product)->create([
                    'shot_id' => sprintf('S%02d', $i),
                    'shot_order' => $i,
                    'product_image_id' => $image->id,
                    'image_url' => $image->local_path,
                    'image_remote_url' => $image->remote_url,
                    'fit' => $image->suggestedFit(),
                ]);
            });
        });
    }
}
