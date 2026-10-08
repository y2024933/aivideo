<?php

declare(strict_types=1);

use App\Enums\BrowserTaskStatus;
use App\Enums\BrowserTaskType;
use App\Enums\ProductStatus;
use App\Models\BrowserTask;
use App\Models\Product;

/**
 * browser:reap-stale 的行為守護。
 *
 * 要守的是「Mac 睡著 / Chromium 被 OOM kill 時，PHP process 直接消失」這個情境：
 * status=running 永遠不會被改掉，Filament 上永遠顯示「執行中」，
 * operator 完全看不出來要介入。
 */
function staleTask(Product $product, int $minutesAgo): BrowserTask
{
    return BrowserTask::factory()->create([
        'type' => BrowserTaskType::ShopeeScrapeProduct,
        'status' => BrowserTaskStatus::Running,
        'started_at' => now()->subMinutes($minutesAgo),
        'subject_type' => $product->getMorphClass(),
        'subject_id' => $product->getKey(),
    ]);
}

it('marks tasks stuck for over 30 minutes as needing manual work', function () {
    $product = Product::factory()->status(ProductStatus::Importing)->create();
    $task = staleTask($product, 45);

    $this->artisan('browser:reap-stale')->assertSuccessful();

    $task->refresh();

    expect($task->status)->toBe(BrowserTaskStatus::NeedsManual)
        ->and($task->error_code)->toBe('worker_vanished')
        ->and($task->finished_at)->not->toBeNull()
        // 錯誤訊息必須講人話並指出該做什麼 —— silent fail 的代價是
        // operator 以為系統壞了然後手動重按十次，那才是真的會把帳號玩掉
        ->and($task->error_message)->toContain('45 分鐘')
        ->and($task->error_message)->toContain('Mac');
});

it('transitions the subject product to needs manual with a reason', function () {
    $product = Product::factory()->status(ProductStatus::Importing)->create();
    staleTask($product, 40);

    $this->artisan('browser:reap-stale')->assertSuccessful();

    $product->refresh();

    expect($product->status)->toBe(ProductStatus::NeedsManual)
        ->and($product->needs_manual_reason)->toContain('瀏覽器任務')
        ->and($product->statusHistory()->first()->triggered_by)->toBe('reap-stale');
});

it('leaves tasks inside the threshold alone', function () {
    $product = Product::factory()->status(ProductStatus::Importing)->create();
    // 29 分鐘：最壞情況的正常執行（1 次初試 + 3 次重試 × 120 秒 timeout
    // + backoff 10/30/90）大約 10 分鐘，再加 worker 的 600 秒 timeout，
    // 門檻必須留足夠餘裕，否則會把還在跑的任務殺掉
    $task = staleTask($product, 29);

    $this->artisan('browser:reap-stale')->assertSuccessful();

    expect($task->refresh()->status)->toBe(BrowserTaskStatus::Running)
        ->and($product->refresh()->status)->toBe(ProductStatus::Importing);
});

it('leaves finished tasks alone', function () {
    $succeeded = BrowserTask::factory()->succeeded()->create(['started_at' => now()->subHours(5)]);
    $failed = BrowserTask::factory()->failed()->create(['started_at' => now()->subHours(5)]);

    $this->artisan('browser:reap-stale')->assertSuccessful();

    expect($succeeded->refresh()->status)->toBe(BrowserTaskStatus::Succeeded)
        ->and($failed->refresh()->status)->toBe(BrowserTaskStatus::Failed);
});

it('honours a custom threshold', function () {
    $product = Product::factory()->status(ProductStatus::Importing)->create();
    $task = staleTask($product, 12);

    $this->artisan('browser:reap-stale', ['--minutes' => 10])->assertSuccessful();

    expect($task->refresh()->status)->toBe(BrowserTaskStatus::NeedsManual);
});

it('reports when there is nothing to reap', function () {
    $this->artisan('browser:reap-stale')
        ->expectsOutputToContain('沒有卡住超過 30 分鐘的瀏覽器任務')
        ->assertSuccessful();
});

it('records the reason but keeps the status when the transition is illegal', function () {
    // ⚠️ 刻意不用 forceStatus()：ProductStatus::TRANSITIONS 不是裝飾品。
    //    已發布的商品不該被一個過期的抓取任務拉回 needs_manual。
    $product = Product::factory()->status(ProductStatus::Published)->create();
    $task = staleTask($product, 60);

    $this->artisan('browser:reap-stale')->assertSuccessful();

    $product->refresh();

    expect($task->refresh()->status)->toBe(BrowserTaskStatus::NeedsManual)
        ->and($product->status)->toBe(ProductStatus::Published)
        // 狀態不動，但原因還是要寫 —— operator 要看得到發生過什麼
        ->and($product->needs_manual_reason)->toContain('瀏覽器任務');
});

it('survives tasks without a subject', function () {
    // session check 這類任務沒有對應的 Product
    $task = BrowserTask::factory()->create([
        'type' => BrowserTaskType::ShopeeSessionCheck,
        'status' => BrowserTaskStatus::Running,
        'started_at' => now()->subHour(),
        'subject_type' => null,
        'subject_id' => null,
    ]);

    $this->artisan('browser:reap-stale')->assertSuccessful();

    expect($task->refresh()->status)->toBe(BrowserTaskStatus::NeedsManual);
});
