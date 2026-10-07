<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductImage>
 */
final class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    public function definition(): array
    {
        $hash = Str::random(32);

        return [
            'product_id' => Product::factory(),
            'sort_order' => 0,
            'source' => 'shopee',
            'source_url' => "https://down-tw.img.susercontent.com/file/{$hash}",
            'image_hash' => $hash,
            'local_path' => "/storage/products/{$hash}.jpg",
            'remote_url' => "https://remotionlambda-test.s3.amazonaws.com/products/{$hash}.jpg",
            // 蝦皮主圖幾乎都是 1:1，是主要測試情境
            'width' => 1000,
            'height' => 1000,
            'bytes' => 184320,
            'mime' => 'image/jpeg',
            'is_primary' => false,
            'is_selected' => true,
            'status' => 'done',
            'license_status' => 'unverified',
        ];
    }

    /** 直式長圖，suggestedFit() 會回 cover */
    public function portrait(): self
    {
        return $this->state(fn () => ['width' => 1080, 'height' => 1920]);
    }

    /** 沒有 S3 副本，用來測 renderBlockers */
    public function noRemote(): self
    {
        return $this->state(fn () => ['remote_url' => null]);
    }

    public function primary(): self
    {
        return $this->state(fn () => ['is_primary' => true, 'sort_order' => 0]);
    }
}
