<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Exceptions\IllegalStatusTransition;
use App\Models\Product;
use App\Models\ProductStatusHistory;

/**
 * 狀態機本身的測試（不是 pipeline 的測試）。
 *
 * ⚠️ 分界：Product::transitionTo() 只查 ProductStatus::TRANSITIONS 白名單，
 *    「素材齊不齊、合規過沒過、renderBlockers() 是否為空」一律不管 ——
 *    那些 gate 住在 Filament action 與 Pipeline 裡（見 ProductResource::approvalBlockers()
 *    / scriptApprovalBlockers() / Product::renderBlockers()）。
 *    所以這裡可以不建任何素材就一路走到 Completed，這是刻意的，不要在 transitionTo()
 *    裡補 gate 來「讓它更安全」—— 會讓 Pipeline 的重試路徑整條斷掉。
 *
 * 為什麼要有這個檔：白名單驗證當初被加進來的理由，就是舊版 transitionTo() 完全不驗證，
 * 讓「Completed 不可到達」與「ScriptPendingReview 是孤兒狀態」這兩個 bug 長期沒被發現。
 * 白名單只是工具，真正的回歸保護是下面那組「結構性守護」—— 它們會在有人改壞 TRANSITIONS
 * 的當下就炸，而不是等到 production 卡住。
 */

/** 從 draft 一路走到 completed 的完整快樂路徑（每一步都必須在 TRANSITIONS 裡） */
const HAPPY_PATH = [
    ProductStatus::Importing,
    ProductStatus::ProductPendingReview,
    ProductStatus::ProductApproved,
    ProductStatus::ScriptGenerating,
    ProductStatus::ScriptPendingReview,
    ProductStatus::ScriptApproved,
    ProductStatus::AssetsGenerating,
    ProductStatus::AssetsPendingReview,
    ProductStatus::AssetsApproved,
    ProductStatus::Rendering,
    ProductStatus::FinalPendingReview,
    ProductStatus::ReadyToPublish,
    ProductStatus::Publishing,
    ProductStatus::PublishDraftFilled,
    ProductStatus::Published,
    ProductStatus::Completed,
];

/** TRANSITIONS 的反向索引：status value => 有哪些狀態能轉進來 */
function inEdges(): array
{
    $in = array_fill_keys(array_keys(ProductStatus::TRANSITIONS), []);

    foreach (ProductStatus::TRANSITIONS as $from => $tos) {
        foreach ($tos as $to) {
            $in[$to][] = $from;
        }
    }

    return $in;
}

// ─────────────────────────────────────────────────────────────
// 1. 完整快樂路徑 —— 這條測試唯一的價值就是「證明 Completed 真的可達」
// ─────────────────────────────────────────────────────────────

it('walks the full happy path from draft to completed', function () {
    $product = Product::factory()->create();

    expect($product->status)->toBe(ProductStatus::Draft);

    $from = ProductStatus::Draft;

    foreach (HAPPY_PATH as $to) {
        // 先確認這一步合法，失敗時錯誤訊息才看得出是哪一段斷掉
        expect($from->canTransitionTo($to))->toBeTrue("{$from->value} → {$to->value} 不在 TRANSITIONS");

        $product->transitionTo($to, 'system', "走到 {$to->value}");

        expect($product->refresh()->status)->toBe($to);

        $this->assertDatabaseHas('product_status_history', [
            'product_id' => $product->id,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'triggered_by' => 'system',
            'note' => "走到 {$to->value}",
        ]);

        $from = $to;
    }

    expect($product->status)->toBe(ProductStatus::Completed)
        ->and($product->statusHistory()->count())->toBe(count(HAPPY_PATH))
        // 歷程完整可回放：每一筆的 from 必須接上上一筆的 to
        ->and($product->statusHistory()->orderBy('id')->pluck('to_status')->all())
        ->toBe(array_map(fn (ProductStatus $s) => $s->value, HAPPY_PATH))
        ->and($product->statusHistory()->orderBy('id')->pluck('from_status')->all())
        ->toBe(['draft', ...array_map(fn (ProductStatus $s) => $s->value, array_slice(HAPPY_PATH, 0, -1))]);
});

it('records nothing and keeps the status when a transition is rejected', function () {
    $product = Product::factory()->create();

    expect(fn () => $product->transitionTo(ProductStatus::Completed))->toThrow(IllegalStatusTransition::class);

    // 對照組：合法轉移「會」寫 history，所以上面的 0 筆不是因為 history 整個壞掉
    expect($product->refresh()->status)->toBe(ProductStatus::Draft)
        ->and($product->statusHistory()->count())->toBe(0);

    $product->transitionTo(ProductStatus::Importing);

    expect($product->refresh()->status)->toBe(ProductStatus::Importing)
        ->and($product->statusHistory()->count())->toBe(1);
});

// ─────────────────────────────────────────────────────────────
// 2. 非法轉移
// ─────────────────────────────────────────────────────────────

it('rejects illegal transitions', function (ProductStatus $from, ProductStatus $to) {
    $product = Product::factory()->status($from)->create();

    expect($from->canTransitionTo($to))->toBeFalse()
        ->and(fn () => $product->transitionTo($to))
        ->toThrow(IllegalStatusTransition::class, "不允許的狀態轉移：{$from->value} → {$to->value}");

    expect($product->refresh()->status)->toBe($from);
})->with([
    // 跳過全部 checkpoint 直接完成
    'draft → completed' => [ProductStatus::Draft, ProductStatus::Completed],
    // 完成後只能封存
    'completed → draft' => [ProductStatus::Completed, ProductStatus::Draft],
    'completed → publishing' => [ProductStatus::Completed, ProductStatus::Publishing],
    // 已發布的影片不能再重渲染（會覆蓋掉上架用的檔案）
    'published → rendering' => [ProductStatus::Published, ProductStatus::Rendering],
    // 封存是終點，解封存必須走 forceStatus()
    'archived → draft' => [ProductStatus::Archived, ProductStatus::Draft],
    // 沒寫稿就想生素材
    'product_approved → assets_generating' => [ProductStatus::ProductApproved, ProductStatus::AssetsGenerating],
]);

// ─────────────────────────────────────────────────────────────
// 3. 結構性守護 —— 純靜態分析 TRANSITIONS，不需要建 model
// ─────────────────────────────────────────────────────────────

it('has exactly one transition list per enum case', function () {
    expect(ProductStatus::cases())->toHaveCount(24)
        ->and(ProductStatus::TRANSITIONS)->toHaveCount(count(ProductStatus::cases()))
        // 新增 case 忘記補表 / 表裡有不存在的 case，兩個方向都要炸
        ->and(array_keys(ProductStatus::TRANSITIONS))
        ->toEqualCanonicalizing(array_map(fn (ProductStatus $s) => $s->value, ProductStatus::cases()));
});

it('only uses valid status values as keys and targets', function () {
    foreach (ProductStatus::TRANSITIONS as $from => $tos) {
        expect(ProductStatus::tryFrom((string) $from))->not->toBeNull("TRANSITIONS 的 key「{$from}」不是合法 status");

        foreach ($tos as $to) {
            expect(ProductStatus::tryFrom((string) $to))->not->toBeNull("{$from} 的目標「{$to}」不是合法 status");
        }

        // 同一個目標寫兩次通常是手動合併時的疏漏
        expect(array_values($tos))->toBe(array_values(array_unique($tos)), "{$from} 的清單有重複目標");
    }
});

it('can reach completed from draft', function () {
    // 從 draft 做 BFS。這是「Completed 不可到達」那個 bug 的靜態回歸測試。
    $seen = ['draft' => true];
    $queue = ['draft'];

    while ($queue !== []) {
        foreach (ProductStatus::TRANSITIONS[array_shift($queue)] as $next) {
            if (! isset($seen[$next])) {
                $seen[$next] = true;
                $queue[] = $next;
            }
        }
    }

    expect($seen)->toHaveKey('completed');

    // 順便確認沒有其他孤島：draft 出發應該走得到全部 24 個狀態
    expect(array_keys($seen))->toEqualCanonicalizing(array_map(fn (ProductStatus $s) => $s->value, ProductStatus::cases()));
});

it('has at least one inbound edge for every status', function () {
    $in = inEdges();

    foreach (ProductStatus::cases() as $case) {
        if ($case === ProductStatus::Draft) {
            continue;   // draft 是起點，由 Product::$attributes 直接給，不必有入邊
        }

        expect($in[$case->value])->not->toBe([], "{$case->value} 是孤兒狀態（沒有任何狀態能轉進來）");
    }
});

it('has at least one outbound edge for every status except archived', function () {
    foreach (ProductStatus::cases() as $case) {
        $tos = ProductStatus::TRANSITIONS[$case->value];

        $case === ProductStatus::Archived
            ? expect($tos)->toBe([], 'archived 是終點，解封存走 forceStatus()')
            : expect($tos)->not->toBe([], "{$case->value} 是死胡同（進去就出不來）");
    }
});

it('allows archiving from every status', function () {
    foreach (ProductStatus::cases() as $case) {
        if ($case === ProductStatus::Archived) {
            continue;
        }

        expect($case->canTransitionTo(ProductStatus::Archived))->toBeTrue("{$case->value} 不能封存");
    }
});

it('never lets rendering transition back into itself', function () {
    // 回歸測試：舊版 PollRemotionRenderJob 失敗時會「轉回原狀態」，
    // rendering → rendering 讓商品永遠停在渲染中而輪詢永遠不結束。
    expect(ProductStatus::TRANSITIONS['rendering'])->not->toContain('rendering')
        ->and(ProductStatus::Rendering->canTransitionTo(ProductStatus::Rendering))->toBeFalse();
});

it('allows only the re-submittable checkpoints to transition into themselves', function () {
    // 自我轉移代表「同一狀態可以重複送審」（operator 連點、重跑合規）。
    // 處理中狀態一旦允許自我轉移就會像舊版 rendering 一樣卡死，所以要白名單管理。
    $selfLoops = array_keys(array_filter(
        ProductStatus::TRANSITIONS,
        fn (array $tos, string $from) => in_array($from, $tos, true),
        ARRAY_FILTER_USE_BOTH,
    ));

    expect($selfLoops)->toEqualCanonicalizing(['product_pending_review', 'script_pending_review', 'ready_to_publish']);

    foreach (ProductStatus::cases() as $case) {
        if ($case->isProcessing()) {
            expect($case->canTransitionTo($case))->toBeFalse("處理中狀態 {$case->value} 不可自我轉移");
        }
    }
});

it('resolves label, color and icon for every case', function () {
    // getLabel()/getColor()/getIcon() 的 match 沒有 default，漏一個 arm 就 UnhandledMatchError
    foreach (ProductStatus::cases() as $case) {
        expect($case->getLabel())->toBeString()->not->toBe('')
            ->and($case->getColor())->toBeString()->not->toBe('')
            ->and($case->getIcon())->toStartWith('heroicon-');
    }

    // 標籤必須唯一，否則後台下拉選單會出現兩個一樣的選項
    $labels = array_map(fn (ProductStatus $s) => $s->getLabel(), ProductStatus::cases());

    expect($labels)->toHaveCount(count(array_unique($labels)));
});

it('classifies the four checkpoints and the processing states', function () {
    expect(array_values(array_filter(ProductStatus::cases(), fn (ProductStatus $s) => $s->isCheckpoint())))
        ->toBe([
            ProductStatus::ProductPendingReview,
            ProductStatus::ScriptPendingReview,
            ProductStatus::AssetsPendingReview,
            ProductStatus::FinalPendingReview,
        ])
        ->and(array_map(fn (ProductStatus $s) => $s->value, array_values(array_filter(ProductStatus::cases(), fn (ProductStatus $s) => $s->isProcessing()))))
        ->toBe(['importing', 'script_generating', 'assets_generating', 'rendering', 'publishing']);

    // checkpoint 與處理中互斥：UI 會同時依這兩個判斷「要不要鎖操作」
    foreach (ProductStatus::cases() as $case) {
        expect($case->isCheckpoint() && $case->isProcessing())->toBeFalse("{$case->value} 同時是 checkpoint 與處理中");
    }
});

// ─────────────────────────────────────────────────────────────
// 4. forceStatus()：繞過白名單
// ─────────────────────────────────────────────────────────────

it('bypasses the whitelist with forceStatus and still writes history', function () {
    $product = Product::factory()->status(ProductStatus::Archived)->create();

    // 對照組：同一組 from/to 走 transitionTo() 是被擋的
    expect(fn () => $product->transitionTo(ProductStatus::Draft))->toThrow(IllegalStatusTransition::class);

    $product->forceStatus(ProductStatus::Draft, 'admin', '手動解除封存（繞過狀態機驗證）');

    expect($product->refresh()->status)->toBe(ProductStatus::Draft);

    $row = $product->statusHistory()->sole();

    expect($row->from_status)->toBe('archived')
        ->and($row->to_status)->toBe('draft')
        ->and($row->triggered_by)->toBe('admin')
        // note 必須留下「這是繞過的」痕跡，否則事後看歷程會以為白名單允許這條
        ->and($row->note)->toContain('繞過');
});

it('keeps forceStatus idempotent for a same-status write', function () {
    $product = Product::factory()->status(ProductStatus::Rendering)->create();

    $product->forceStatus(ProductStatus::Rendering, 'admin', '重設渲染狀態');

    expect($product->refresh()->status)->toBe(ProductStatus::Rendering)
        ->and($product->statusHistory()->sole()->from_status)->toBe('rendering');
});

// ─────────────────────────────────────────────────────────────
// 5. 封存 / 解封存
// ─────────────────────────────────────────────────────────────

it('archives a product from any status', function (ProductStatus $from) {
    $product = Product::factory()->status($from)->create();

    $product->transitionTo(ProductStatus::Archived, 'operator', '專案取消');

    expect($product->refresh()->status)->toBe(ProductStatus::Archived)
        ->and($product->statusHistory()->sole()->from_status)->toBe($from->value);

    // 封存後完全出不去（TRANSITIONS['archived'] 是空陣列）
    expect(ProductStatus::TRANSITIONS['archived'])->toBe([]);

    foreach (ProductStatus::cases() as $to) {
        expect(fn () => $product->transitionTo($to))->toThrow(IllegalStatusTransition::class);
    }
})->with(function () {
    $cases = array_values(array_filter(ProductStatus::cases(), fn (ProductStatus $s) => $s !== ProductStatus::Archived));

    return array_combine(
        array_map(fn (ProductStatus $s) => $s->value, $cases),
        array_map(fn (ProductStatus $s) => [$s], $cases),
    );
});

// ─────────────────────────────────────────────────────────────
// 6. ProductStatusHistory model
// ─────────────────────────────────────────────────────────────

it('uses the singular product_status_history table name', function () {
    // Laravel 會把 ProductStatusHistory 推成 product_status_histories，
    // 少了 $table 宣告整個狀態機的寫入都會噴「table not found」。
    expect((new ProductStatusHistory)->getTable())->toBe('product_status_history');
});

it('relates history rows back to the product', function () {
    $product = Product::factory()->create();
    $product->transitionTo(ProductStatus::Importing, 'system');

    $row = ProductStatusHistory::query()->sole();

    expect($row->product)->toBeInstanceOf(Product::class)
        ->and($row->product->is($product))->toBeTrue()
        ->and($product->statusHistory->first()->id)->toBe($row->id);
});

it('returns status history newest first', function () {
    $product = Product::factory()->create();

    $product->transitionTo(ProductStatus::Importing, 'system');
    $this->travel(1)->seconds();
    $product->transitionTo(ProductStatus::ProductPendingReview, 'system');

    expect($product->statusHistory()->pluck('to_status')->all())->toBe(['product_pending_review', 'importing']);
});
