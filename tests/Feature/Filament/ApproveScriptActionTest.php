<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Jobs\GenerateAssetsJob;
use App\Jobs\GenerateScriptJob;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shot;
use App\Models\User;
use App\Services\Contracts\ScriptWriterContract;
use App\Services\Stubs\StubScriptWriter;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    // 核准 ② 後會排素材 Job；這裡只測 checkpoint 本身，不讓 sync queue 一路跑到渲染
    Queue::fake();
});

/** 跑完 P3 寫稿、停在 checkpoint ② 的商品 */
function pendingReviewProduct(): Product
{
    $product = Product::factory()->status(ProductStatus::ProductApproved)->create();

    foreach (range(1, 3) as $i) {
        ProductImage::factory()->for($product)->create(['sort_order' => $i, 'is_primary' => $i === 1]);
    }

    app()->instance(ScriptWriterContract::class, new StubScriptWriter());
    dispatch_sync(new GenerateScriptJob($product->id));

    return $product->refresh();
}

function edit(Product $product): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test(EditProduct::class, ['record' => $product->getKey()]);
}

it('生成腳本按鈕只在商品已核准或腳本失敗時出現，並派出 Job', function () {
    Queue::fake();

    $product = Product::factory()->status(ProductStatus::ProductApproved)->create();

    edit($product)->assertActionVisible('generateScript')->callAction('generateScript');
    Queue::assertPushed(GenerateScriptJob::class);

    $product->forceStatus(ProductStatus::ScriptPendingReview, 'test');
    edit($product->refresh())->assertActionHidden('generateScript');
});

it('乾淨的腳本可以核准並轉為腳本已核准', function () {
    $product = pendingReviewProduct();

    expect($product->status)->toBe(ProductStatus::ScriptPendingReview);

    edit($product)->callAction('approveScript');

    expect($product->refresh()->status)->toBe(ProductStatus::ScriptApproved)
        ->and($product->compliance_passed)->toBeTrue();
    Queue::assertPushed(GenerateAssetsJob::class, fn (GenerateAssetsJob $job) => $job->productId === $product->id);
});

it('有 blocking 違規時拒絕核准且狀態不變', function () {
    $product = pendingReviewProduct();
    $product->shots()->where('shot_id', 'S02')->update(['subtitle' => '保證最有效，三天見效']);

    edit($product)->callAction('approveScript')->assertNotified('不可核准');

    expect($product->refresh()->status)->toBe(ProductStatus::ScriptPendingReview)
        ->and($product->compliance_passed)->toBeFalse()
        ->and(ProductResource::checkScript($product)->blocking())->not->toBeEmpty();
});

it('operator 手改字幕成簡體字後會重跑合規並拒絕', function () {
    $product = pendingReviewProduct();

    // 直接改 DB，不碰 subtitle_has_simplified 旗標 —— 模擬 operator 從鏡頭列表改字。
    // gate 若只讀舊報告或舊旗標，這裡就會誤放行。
    $product->shots()->where('shot_id', 'S03')->update(['subtitle' => '这款真的很安静']);

    expect($product->shots()->where('shot_id', 'S03')->value('subtitle_has_simplified'))->toBeFalsy();

    edit($product)->callAction('approveScript')->assertNotified('不可核准');

    expect($product->refresh()->status)->toBe(ProductStatus::ScriptPendingReview)
        // 核准被擋的同時也要把旗標與報告更新成「現在的字」，operator 才看得到問題在哪
        ->and($product->shots()->where('shot_id', 'S03')->value('subtitle_has_simplified'))->toBeTruthy()
        ->and($product->compliance_report['findings'])->not->toBeEmpty();
});

it('warning 未全部確認時拒絕核准，全部確認後可核准', function () {
    // 「最推薦」是 superlative 的 warning 詞（公平法 §21 最高級用語需客觀數據佐證）。
    // ⚠️ 不要用「降噪」「續航」這類品類詞 —— 它們原本在 spec_overclaim 的詞表裡，
    //    但商品標題本身就含這些字，當成違規是誤判，已從規則移除。
    $product = pendingReviewProduct();
    $product->shots()->where('shot_id', 'S03')->update(['subtitle' => '這款我最推薦']);

    edit($product)->callAction('approveScript')->assertNotified('不可核准');
    $product->refresh();

    $warnings = App\Data\ComplianceReport::from($product->compliance_report)->unacknowledgedWarnings();

    expect($warnings)->not->toBeEmpty()
        ->and($product->status)->toBe(ProductStatus::ScriptPendingReview);

    foreach ($warnings as $warning) {
        edit($product->refresh())->callAction('acknowledgeFinding', arguments: ['key' => ProductResource::findingKey($warning)]);
    }

    $product->refresh();

    expect(App\Data\ComplianceReport::from($product->compliance_report)->unacknowledgedWarnings())->toBe([])
        ->and($product->compliance_passed)->toBeTrue();

    edit($product)->callAction('approveScript');

    expect($product->refresh()->status)->toBe(ProductStatus::ScriptApproved);
});

it('blocking 項目不可被 acknowledge', function () {
    $product = pendingReviewProduct();
    $product->shots()->where('shot_id', 'S02')->update(['subtitle' => '保證最有效']);
    ProductResource::storeComplianceReport($product, ProductResource::checkScript($product));
    $product->refresh();

    $blocking = App\Data\ComplianceReport::from($product->compliance_report)->blocking();

    expect($blocking)->not->toBeEmpty();

    foreach ($blocking as $finding) {
        edit($product->refresh())->callAction('acknowledgeFinding', arguments: ['key' => ProductResource::findingKey($finding)]);
    }

    $product->refresh();
    $report = App\Data\ComplianceReport::from($product->compliance_report);

    expect($report->blocking())->not->toBeEmpty()
        ->and(collect($report->blocking())->every(fn ($f) => ! $f->acknowledged))->toBeTrue()
        ->and($product->compliance_passed)->toBeFalse();

    edit($product)->callAction('approveScript');

    expect($product->refresh()->status)->toBe(ProductStatus::ScriptPendingReview);
});

it('沒有鏡頭時不可核准', function () {
    $product = pendingReviewProduct();
    $product->shots()->delete();

    edit($product->refresh())->callAction('approveScript')->assertNotified('不可核准');

    expect($product->refresh()->status)->toBe(ProductStatus::ScriptPendingReview);
});

it('合規面板顯示 blocking 的法條依據與簡體字候選', function () {
    $product = pendingReviewProduct();
    $product->shots()->where('shot_id', 'S02')->update(['subtitle' => '保證有效，头发也不乾']);
    ProductResource::storeComplianceReport($product, ProductResource::checkScript($product));

    edit($product->refresh())
        ->assertSee('公平交易法')
        ->assertSee('必須修正')
        ->assertSee('簡體字')
        ->assertSee('發')     // 发 的候選之一
        ->assertSuccessful();
});

it('核准腳本按鈕只在腳本待審核狀態出現', function () {
    $product = Product::factory()->status(ProductStatus::ProductApproved)->create();

    edit($product)->assertActionHidden('approveScript');

    $product->forceStatus(ProductStatus::ScriptPendingReview, 'test');

    edit($product->refresh())->assertActionVisible('approveScript');
});

it('腳本核准後鏡頭的簡體字旗標與 compliance_flags 會被同步', function () {
    $product = pendingReviewProduct();

    edit($product)->callAction('approveScript');

    expect($product->shots()->get()->every(fn (Shot $shot) => $shot->compliance_flags === []))->toBeTrue()
        ->and($product->shots()->where('subtitle_has_simplified', true)->count())->toBe(0);
});
