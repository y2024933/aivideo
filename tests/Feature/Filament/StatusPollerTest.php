<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

/**
 * Filament 的 EditRecord 把 $view 寫死在 vendor（EditRecord.php:45），手寫在
 * resources/views/filament/resources/.../edit-*.blade.php 的 wire:poll 從來不會被渲染，
 * 也沒有 getPollingInterval() 可用。唯一可行的掛點是 form 裡的 ViewField。
 */
beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('renders wire:poll while the product is processing', function () {
    $product = Product::factory()->status(ProductStatus::Importing)->create();

    Livewire::test(EditProduct::class, ['record' => $product->getKey()])
        ->assertSee('wire:poll.5s', escape: false)
        ->assertSee('商品匯入中');
});

it('does not poll when the product is idle', function () {
    $product = Product::factory()->status(ProductStatus::Draft)->create();

    Livewire::test(EditProduct::class, ['record' => $product->getKey()])
        ->assertDontSee('wire:poll.5s', escape: false);
});

// bare wire:poll == $refresh，這個測試就是模擬 5 秒後那一次輪詢
it('picks up a background status change on refresh', function () {
    $product = Product::factory()->status(ProductStatus::Importing)->create();

    $page = Livewire::test(EditProduct::class, ['record' => $product->getKey()])
        ->assertSee('wire:poll.5s', escape: false);

    // 模擬 queue worker 在背景推進狀態
    Product::whereKey($product->getKey())->update(['status' => ProductStatus::ProductPendingReview->value]);

    $page->call('$refresh')
        ->assertSee('① 商品資料待確認')
        ->assertDontSee('wire:poll.5s', escape: false);
});
