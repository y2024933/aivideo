<?php

declare(strict_types=1);

use App\Enums\BrowserTaskStatus;
use App\Enums\ProductStatus;
use App\Filament\Widgets\BrowserHealthWidget;
use App\Filament\Widgets\CostSummaryWidget;
use App\Models\BrowserTask;
use App\Models\Product;
use App\Models\User;
use App\Services\Browser\BrowserHealth;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
    $this->actingAs(User::factory()->create());
});

it('renders the browser health widget', function () {
    Livewire::test(BrowserHealthWidget::class)->assertSuccessful();
});

it('tells the operator that queued work resumes when the mac comes back', function () {
    // 這句話是刻意寫進 UI 的：離線時 operator 唯一會做的事是去重按重試按鈕，
    // 而那會把每小時的 rate limit 用光，反而提高帳號被風控的機率。
    Livewire::test(BrowserHealthWidget::class)
        ->assertSuccessful()
        ->assertSee('離線')
        ->assertSee('瀏覽器任務會留在佇列，Mac 開機後自動繼續。');
});

it('shows the worker as healthy right after a heartbeat', function () {
    Cache::put(BrowserHealth::HEARTBEAT_KEY, now()->toIso8601String(), 300);
    Cache::put(BrowserHealth::HEARTBEAT_HOST_KEY, 'paul-macbook', 300);

    Livewire::test(BrowserHealthWidget::class)
        ->assertSuccessful()
        ->assertSee('正常')
        ->assertSee('paul-macbook')
        ->assertDontSee('瀏覽器任務會留在佇列，Mac 開機後自動繼續。');
});

it('surfaces how long the oldest pending task has waited', function () {
    BrowserTask::factory()->create(['queued_at' => now()->subMinutes(42)]);

    Livewire::test(BrowserHealthWidget::class)
        ->assertSuccessful()
        ->assertSee('最舊已等 42 分鐘');
});

it('links stuck tasks to the filtered browser task list', function () {
    BrowserTask::factory()->count(3)->needsManual()->create();

    Livewire::test(BrowserHealthWidget::class)
        ->assertSuccessful()
        ->assertSee('卡住的任務')
        ->assertSee('tableFilters%5Bstatus%5D%5Bvalues%5D%5B0%5D=' . BrowserTaskStatus::NeedsManual->value, escape: false);
});

it('polls every 30 seconds', function () {
    // StatsOverviewWidget 用了 Concerns\CanPoll，getPollingInterval() 是真的存在的 API。
    // 心跳門檻是 2 分鐘，輪詢必須比它快才看得到狀態變化。
    $interval = (new ReflectionMethod(BrowserHealthWidget::class, 'getPollingInterval'))
        ->invoke(new BrowserHealthWidget);

    expect($interval)->toBe('30s');
});

it('renders the cost summary widget', function () {
    Livewire::test(CostSummaryWidget::class)->assertSuccessful();
});

it('adds the two mutually exclusive cost columns together', function () {
    // ⚠️ Product::addLlmCost() 刻意不累加進 cost_usd，兩欄是互斥的。
    //    只顯示其中一欄會系統性低估，而 LLM 寫稿常常是單支影片最貴的一項。
    Product::factory()->status(ProductStatus::Completed)->create([
        'cost_usd' => 0.40,
        'llm_cost_usd' => 0.12,
    ]);

    Livewire::test(CostSummaryWidget::class)
        ->assertSuccessful()
        ->assertSee('$0.52')     // 總成本
        ->assertSee('$0.12')     // LLM
        ->assertSee('$0.40');    // 其他
});

it('counts only delivered products when averaging cost', function () {
    Product::factory()->status(ProductStatus::Completed)->create(['cost_usd' => 0.50, 'llm_cost_usd' => 0.10]);
    // 草稿不該算進「產出支數」，否則平均成本會被稀釋成好看但無意義的數字
    Product::factory()->status(ProductStatus::Draft)->create(['cost_usd' => 0, 'llm_cost_usd' => 0]);

    Livewire::test(CostSummaryWidget::class)
        ->assertSuccessful()
        ->assertSee('平均每支 $0.600');
});

it('does not divide by zero when nothing shipped this month', function () {
    Livewire::test(CostSummaryWidget::class)
        ->assertSuccessful()
        ->assertSee('本月尚無完成的影片');
});

it('exposes both widgets on the admin dashboard', function () {
    $widgets = array_map(
        fn ($widget) => is_string($widget) ? $widget : $widget::class,
        filament()->getPanel('admin')->getWidgets(),
    );

    expect($widgets)->toContain(BrowserHealthWidget::class)->toContain(CostSummaryWidget::class);
});

it('serves the admin dashboard with both widgets mounted', function () {
    $this->get('/admin')
        ->assertSuccessful()
        ->assertSee('瀏覽器任務健康狀態')
        ->assertSee('本月成本');
});
