<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ProductStatus;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * 本月成本摘要。
 *
 * ⚠️ products 的兩個成本欄位是「互斥」的，不是「總額與細項」：
 *      cost_usd     = 圖片 / 動畫 / 配音 / 剪接（外部算力）
 *      llm_cost_usd = 寫稿（Product::addLlmCost() 刻意不累加進 cost_usd）
 *    所以總成本必須自己相加。只顯示其中一欄會系統性低估 —— 而 LLM 寫稿常常
 *    是單支影片裡最貴的一項，低估的正好是最該盯的那塊。
 */
final class CostSummaryWidget extends StatsOverviewWidget
{
    protected ?string $heading = '本月成本';

    protected static ?int $sort = -19;

    // 同 BrowserHealthWidget：1 CPU 的機器上，少一次 Livewire round trip
    // 比延後兩個聚合查詢划算。
    protected static bool $isLazy = false;

    protected function getPollingInterval(): ?string
    {
        // 成本是累計數字，不需要像健康狀態那樣即時
        return '120s';
    }

    protected function getStats(): array
    {
        $since = CarbonImmutable::now()->startOfMonth();

        // 單一 query 取三個聚合值，避免 widget 每次輪詢打三趟 DB
        $row = Product::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('COALESCE(SUM(cost_usd), 0) as media_cost')
            ->selectRaw('COALESCE(SUM(llm_cost_usd), 0) as llm_cost')
            ->selectRaw('COUNT(*) as product_count')
            ->first();

        $mediaCost = (float) ($row->media_cost ?? 0);
        $llmCost = (float) ($row->llm_cost ?? 0);
        $total = $mediaCost + $llmCost;

        // 「產出支數」只算真的做出影片的，不是建立的商品數 ——
        // 草稿與失敗件會把平均成本稀釋成好看但無意義的數字
        $delivered = Product::query()
            ->where('created_at', '>=', $since)
            ->whereIn('status', [
                ProductStatus::FinalPendingReview,
                ProductStatus::ReadyToPublish,
                ProductStatus::Publishing,
                ProductStatus::PublishDraftFilled,
                ProductStatus::Published,
                ProductStatus::Completed,
            ])
            ->count();

        return [
            Stat::make('本月總成本', '$' . number_format($total, 2))
                ->description(sprintf('%s 起算，共 %d 件商品', $since->format('Y/m/d'), (int) ($row->product_count ?? 0)))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('LLM 寫稿成本', '$' . number_format($llmCost, 2))
                ->description($total > 0 ? sprintf('占總成本 %.0f%%', $llmCost / $total * 100) : '尚無支出')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('gray'),

            Stat::make('其他成本', '$' . number_format($mediaCost, 2))
                ->description('圖片／動畫／配音／剪接')
                ->descriptionIcon('heroicon-m-film')
                ->color('gray'),

            Stat::make('產出支數', (string) $delivered)
                ->description($delivered > 0
                    ? '平均每支 $' . number_format($total / $delivered, 3)
                    : '本月尚無完成的影片')
                ->descriptionIcon('heroicon-m-video-camera')
                ->color('success'),
        ];
    }
}
