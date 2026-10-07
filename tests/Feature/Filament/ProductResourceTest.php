<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Resources\ProductResource\RelationManagers\ImagesRelationManager;
use App\Filament\Resources\ProductResource\RelationManagers\ShotsRelationManager;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('s3');
    $this->actingAs(User::factory()->create());
});

it('lists products', function () {
    $product = Product::factory()->renderable(3)->create();

    Livewire::test(ListProducts::class)->assertCanSeeTableRecords([$product])->assertSuccessful();
});

it('renders the edit page', function () {
    $product = Product::factory()->renderable(2)->create();

    Livewire::test(EditProduct::class, ['record' => $product->getKey()])->assertSuccessful();
});

it('creates a product skeleton from a shopee link', function () {
    Livewire::test(CreateProduct::class)
        ->callAction('createFromShopeeLink', ['url' => 'https://shopee.tw/耳機-i.111.222']);

    $product = Product::sole();

    expect($product->source)->toBe('shopee_link')
        ->and($product->shopee_shop_id)->toBe(111)
        ->and($product->shopee_item_id)->toBe(222)
        ->and($product->source_url)->toBe('https://shopee.tw/product/111/222')
        ->and($product->status)->toBe(ProductStatus::Draft)
        ->and($product->disclosure_prefix)->toBe(config('compliance.disclosure_prefix'));
});

it('does not create a record for an invalid shopee link', function () {
    Livewire::test(CreateProduct::class)->callAction('createFromShopeeLink', ['url' => 'https://example.com/foo']);

    expect(Product::count())->toBe(0);
});

it('redirects to the existing record instead of duplicating', function () {
    $existing = Product::factory()->shopee(111, 222)->create();

    Livewire::test(CreateProduct::class)
        ->callAction('createFromShopeeLink', ['url' => 'https://shopee.tw/product/111/222'])
        ->assertRedirect(ProductResource::getUrl('edit', ['record' => $existing]));

    expect(Product::count())->toBe(1);
});

it('walks draft to product_pending_review to product_approved', function () {
    $product = Product::factory()->renderable(2)->create();

    Livewire::test(EditProduct::class, ['record' => $product->getKey()])->callAction('submitProduct');
    expect($product->refresh()->status)->toBe(ProductStatus::ProductPendingReview);

    Livewire::test(EditProduct::class, ['record' => $product->getKey()])->callAction('approveProduct');
    expect($product->refresh()->status)->toBe(ProductStatus::ProductApproved)
        ->and($product->statusHistory()->pluck('to_status')->all())
        ->toBe(['product_approved', 'product_pending_review']);
});

it('blocks approval when selected images lack an s3 copy', function () {
    $product = Product::factory()->status(ProductStatus::ProductPendingReview)->create();
    ProductImage::factory()->for($product)->create();
    ProductImage::factory()->for($product)->noRemote()->create();

    expect(ProductResource::approvalBlockers($product))->toContain('有 1 張圖尚未同步到 S3');

    Livewire::test(EditProduct::class, ['record' => $product->getKey()])->assertActionDisabled('approveProduct');
});

it('blocks approval with fewer than two selected images', function () {
    $product = Product::factory()->status(ProductStatus::ProductPendingReview)->create(['affiliate_url' => null]);
    ProductImage::factory()->for($product)->create();

    expect(ProductResource::approvalBlockers($product))->toBe(['勾選的圖片不足 2 張', '分潤連結']);
});

it('uploads images with s3 sync and full metadata', function () {
    $product = Product::factory()->create();

    Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
        ->callTableAction('upload', data: [
            'files' => collect(range(1, 5))
                ->map(fn (int $i) => UploadedFile::fake()->image("p{$i}.jpg", 1000, 1000))
                ->all(),
        ]);

    $images = $product->images()->get();

    expect($images)->toHaveCount(5);

    $images->each(function (ProductImage $image) {
        // Remotion Lambda 只讀 remote_url，缺它渲染必 404
        expect($image->remote_url)->not->toBeNull()
            ->and($image->width)->toBe(1000)
            ->and($image->height)->toBe(1000)
            ->and($image->bytes)->toBeGreaterThan(0)
            ->and($image->mime)->toBe('image/jpeg')
            ->and($image->status)->toBe('done');

        Storage::disk('s3')->assertExists(ltrim(str_replace('/storage/', '', $image->local_path), '/'));
    });
});

it('fetches an image from an external url', function () {
    $product = Product::factory()->create();
    $png = (function () {
        $img = imagecreatetruecolor(1080, 1920);
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    })();

    Http::fake(['https://cdn.example.com/x.png' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);

    Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
        ->callTableAction('fetchExternal', data: ['url' => 'https://cdn.example.com/x.png']);

    $image = $product->images()->sole();

    expect($image->source)->toBe('external')
        ->and($image->remote_url)->not->toBeNull()
        ->and($image->height)->toBe(1920)
        ->and($image->suggestedFit())->toBe('cover');
});

it('binds an image to a shot and derives fit from the aspect ratio', function () {
    $product = Product::factory()->create();
    $image = ProductImage::factory()->for($product)->portrait()->create();
    $shot = Shot::factory()->for($product)->create(['fit' => 'contain']);

    Livewire::test(ShotsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
        ->callTableAction('pickImage', $shot, ['product_image_id' => $image->id]);

    expect($shot->refresh()->product_image_id)->toBe($image->id)
        ->and($shot->image_remote_url)->toBe($image->remote_url)
        ->and($shot->fit)->toBe('cover');
});

it('lets an operator edit the per-shot transition override', function () {
    $product = Product::factory()->create(['global_transition' => 'crossfade']);
    $shot = Shot::factory()->for($product)->create();

    expect($shot->effectiveTransition())->toBe('crossfade');

    Livewire::test(ShotsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
        ->callTableAction('edit', $shot, ['transition' => 'clockWipe']);

    expect($shot->refresh()->transition)->toBe('clockWipe')
        ->and($shot->effectiveTransition())->toBe('clockWipe');
});

it('marks image license status for risk tracking', function () {
    $product = Product::factory()->create();
    $image = ProductImage::factory()->for($product)->create();

    Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
        ->callTableAction('markLicense', $image, ['license_status' => 'seller_authorized', 'license_note' => '賣家 LINE 同意']);

    expect($image->refresh()->license_status)->toBe('seller_authorized');
});

it('uploads bgm with s3 sync and forces a license note', function () {
    $product = Product::factory()->create();
    $path = UploadedFile::fake()->create('calm.mp3', 512, 'audio/mpeg')->storeAs('bgm', 'calm.mp3', 'public');

    Livewire::test(EditProduct::class, ['record' => $product->getKey()])
        // FileUpload 的 state 是 uuid => diskPath，fillForm 會被它的 afterStateHydrated 洗掉
        ->set('data.bgm_upload', ['fake-uuid' => $path])
        ->set('data.bgm_license_note', 'Pixabay Music / CC0')
        ->set('data.bgm_volume', 0.2)
        ->call('save')
        ->assertHasNoFormErrors();

    $product->refresh();

    // Remotion Lambda 只讀 bgm_remote_url，缺它等於沒有背景音樂
    expect($product->bgm_url)->toBe('/storage/' . $path)
        ->and($product->bgm_remote_url)->not->toBeNull()
        ->and($product->bgm_license_note)->toBe('Pixabay Music / CC0')
        ->and((float) $product->bgm_volume)->toBe(0.2);

    Storage::disk('s3')->assertExists($path);
});

it('refuses to save a bgm without a license note', function () {
    $product = Product::factory()->create(['bgm_url' => '/storage/bgm/x.mp3', 'bgm_license_note' => null]);

    Livewire::test(EditProduct::class, ['record' => $product->getKey()])
        ->set('data.bgm_license_note', null)
        ->call('save')
        ->assertHasFormErrors(['bgm_license_note']);
});
