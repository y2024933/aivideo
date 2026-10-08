<?php

declare(strict_types=1);

use App\Enums\BrowserTaskStatus;
use App\Filament\Resources\BrowserTaskResource\Pages\ListBrowserTasks;
use App\Filament\Resources\BrowserTaskResource\Pages\ViewBrowserTask;
use App\Jobs\ScrapeShopeeProductJob;
use App\Models\BrowserTask;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('s3');
    $this->actingAs(User::factory()->create());
});

it('lists browser tasks', function () {
    $tasks = BrowserTask::factory()->count(2)->succeeded()->create();

    Livewire::test(ListBrowserTasks::class)->assertCanSeeTableRecords($tasks)->assertSuccessful();
});

it('renders the view page for every outcome', function (string $state) {
    $task = BrowserTask::factory()->{$state}()->create();

    Livewire::test(ViewBrowserTask::class, ['record' => $task->getKey()])->assertSuccessful();
})->with(['succeeded', 'degraded', 'failed', 'needsManual']);

it('shows the failure screenshot and trace links', function () {
    Storage::disk('s3')->put('browser/x/screenshot.png', 'png-bytes');
    Storage::disk('s3')->put('browser/x/trace.zip', 'zip-bytes');

    $task = BrowserTask::factory()->failed()->create([
        'screenshot_path' => 'browser/x/screenshot.png',
        'trace_path' => 'browser/x/trace.zip',
    ]);

    Livewire::test(ViewBrowserTask::class, ['record' => $task->getKey()])
        ->assertSuccessful()
        ->assertSee('等待商品標題選擇器逾時');
});

it('offers a manual retry that ignores the time window', function () {
    // 人就在電腦前面，時段限制本來是為了擋無人值守的批次
    Queue::fake();
    $product = Product::factory()->shopee(111, 222)->create();
    $task = BrowserTask::factory()->failed()->create([
        'subject_type' => $product->getMorphClass(),
        'subject_id' => $product->id,
    ]);

    Livewire::test(ListBrowserTasks::class)->callTableAction('retryScrape', $task);

    Queue::assertPushed(
        ScrapeShopeeProductJob::class,
        fn (ScrapeShopeeProductJob $job) => $job->productId === $product->id && $job->ignoreTimeWindow === true,
    );
});

it('hides the retry action when the task has no product subject', function () {
    $task = BrowserTask::factory()->failed()->create();

    Livewire::test(ListBrowserTasks::class)->assertTableActionHidden('retryScrape', $task);
});

it('warns on the product page when the data came from a degraded scrape', function () {
    $product = Product::factory()->shopee(111, 222)->create();
    BrowserTask::factory()->degraded()->create([
        'subject_type' => $product->getMorphClass(),
        'subject_id' => $product->id,
    ]);

    expect($product->hasDegradedScrape())->toBeTrue();

    Livewire::test(App\Filament\Resources\ProductResource\Pages\EditProduct::class, ['record' => $product->getKey()])
        ->assertSuccessful()
        ->assertSee('降級抓取');
});

it('does not warn when the scrape used the official api', function () {
    $product = Product::factory()->shopee(111, 222)->create();
    BrowserTask::factory()->succeeded()->create([
        'subject_type' => $product->getMorphClass(),
        'subject_id' => $product->id,
    ]);

    expect($product->hasDegradedScrape())->toBeFalse();
});

it('is read only: browser tasks are an audit log, not something to hand craft', function () {
    expect(App\Filament\Resources\BrowserTaskResource::canCreate())->toBeFalse()
        ->and(BrowserTask::factory()->needsManual()->create()->status)->toBe(BrowserTaskStatus::NeedsManual);
});
