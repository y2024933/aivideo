<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BrowserTaskStatus;
use App\Enums\ProductStatus;
use App\Models\BrowserTask;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 回收卡死的瀏覽器任務。
 *
 * 為什麼需要：browser queue 的 worker 在 Mac 上，而 Mac 會睡眠、會被關上蓋子、
 * Chromium 會 OOM 被 kernel kill。這些情況下 job 的 PHP process 直接消失，
 * `markRunning()` 寫下的 status=running 永遠不會被改掉 ——
 * BrowserTask 停在「執行中」、Product 停在 importing，Filament 上看起來像還在跑，
 * operator 永遠等不到結果也不知道要介入。
 *
 * ⚠️ 門檻 30 分鐘不是隨便取的：單次抓取最壞情況是 1 次初試 + 3 次重試
 *    （backoff 10/30/90 秒）× 每次最多 120 秒 timeout ≈ 10 分鐘，再加上
 *    queue worker 的 --timeout=600。超過 30 分鐘一定是 process 已經不在了。
 *
 * ⚠️ 這支指令只在生產機跑（見 App\Console\Kernel）。Mac 上跑的話，Mac 剛睡醒時
 *    可能把自己正在重試的任務標成死亡。
 */
final class BrowserReapStaleCommand extends Command
{
    protected $signature = 'browser:reap-stale {--minutes=30 : running 超過幾分鐘視為卡死}';

    protected $description = '把卡在 running 的瀏覽器任務標成需人工介入（只該在生產機跑）';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));

        /** @var \Illuminate\Database\Eloquent\Collection<int, BrowserTask> $stale */
        $stale = BrowserTask::query()->stale($minutes)->get();

        if ($stale->isEmpty()) {
            $this->info("沒有卡住超過 {$minutes} 分鐘的瀏覽器任務。");

            return self::SUCCESS;
        }

        foreach ($stale as $task) {
            $waited = $task->started_at?->diffInMinutes(now()) ?? $minutes;
            $reason = sprintf(
                '瀏覽器任務「%s」已執行中 %d 分鐘未回報（超過 %d 分鐘門檻），研判執行端已中斷'
                . '（Mac 睡眠／關機／Chromium 被 OOM kill）。請確認 Mac 已開機並重新觸發，'
                . '或到瀏覽器任務頁面看最後的截圖與 trace。',
                $task->type->getLabel(),
                $waited,
                $minutes,
            );

            $task->update([
                'status' => BrowserTaskStatus::NeedsManual,
                'finished_at' => now(),
                'error_code' => 'worker_vanished',
                'error_message' => $reason,
            ]);

            $this->flagSubject($task, $reason);

            $this->warn("已回收 {$task->id}（{$task->type->value}，卡 {$waited} 分鐘）");
        }

        Log::warning('browser:reap-stale 回收了卡死的瀏覽器任務', [
            'count' => $stale->count(),
            'threshold_minutes' => $minutes,
            'task_ids' => $stale->pluck('id')->all(),
        ]);

        $this->info("共回收 {$stale->count()} 筆。");

        return self::SUCCESS;
    }

    /**
     * 把對應的 Product 轉成需人工介入。
     *
     * ⚠️ 刻意不用 forceStatus()：ProductStatus::TRANSITIONS 不是裝飾品，繞過它會讓
     *    已發布／已封存的商品被一個過期的抓取任務拉回 needs_manual。轉移不合法時
     *    只寫 needs_manual_reason —— operator 看得到原因，狀態機保持乾淨。
     */
    private function flagSubject(BrowserTask $task, string $reason): void
    {
        if ($task->subject_type !== (new Product)->getMorphClass() || blank($task->subject_id)) {
            return;
        }

        $product = Product::find($task->subject_id);

        if ($product === null) {
            return;
        }

        $product->update(['needs_manual_reason' => $reason]);

        if (in_array(ProductStatus::NeedsManual->value, ProductStatus::TRANSITIONS[$product->status->value] ?? [], true)) {
            // ⚠️ triggered_by 欄位只有 16 字元（見 create_product_status_history_table），
            //    'browser:reap-stale' 是 18 字元會被 MySQL strict mode 直接拒絕。
            $product->transitionTo(ProductStatus::NeedsManual, 'reap-stale', $reason);

            return;
        }

        $this->line("  └ 商品 {$product->id} 目前是 {$product->status->value}，不允許轉 needs_manual，只寫入原因。");
    }
}
