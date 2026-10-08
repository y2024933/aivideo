<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AudioMode;
use App\Enums\ProductStatus;
use App\Filament\Resources\ProductResource;
use App\Jobs\GenerateAssetsJob;
use App\Jobs\GenerateScriptJob;
use App\Jobs\SubmitRenderJob;
use App\Models\Product;
use App\Models\Shot;
use App\Services\Video\VideoGeneratorFactory;
use Illuminate\Support\Facades\DB;

/**
 * Pipeline 的接力點：每個階段結束後該派哪個 Job、哪些 checkpoint 可以自動放行。
 *
 * 自動放行（autopilot）走「例外才看」：gate 全部通過才放行，任何一條不過就停在
 * checkpoint 等人。開關在 config('video.autopilot')。
 * ② 腳本預設關閉 —— 違規的法律責任在 operator 身上，要自動放行必須自己打開。
 */
final class Pipeline
{
    private const AUTOPILOT = 'autopilot';

    /**
     * ① 送審後：資料齊全就自動核准，直接開始寫稿。
     *
     * ⚠️ 降級抓取（strategy = dom）絕不自動放行。DOM 解析拿到的價格與規格可能缺漏或
     * 錯位，而 ① 之後就是花錢寫稿、生素材、渲染 —— 資料來源不可靠時一定要人看一眼。
     */
    public function afterProductSubmitted(Product $product): void
    {
        if ($product->status !== ProductStatus::ProductPendingReview
            || ! config('video.autopilot.product')
            || $product->hasDegradedScrape()
            || ProductResource::approvalBlockers($product) !== []) {
            return;
        }

        $product->transitionTo(ProductStatus::ProductApproved, self::AUTOPILOT);
        $this->afterProductApproved($product);
    }

    public function afterProductApproved(Product $product): void
    {
        GenerateScriptJob::dispatch($product->id);
    }

    /**
     * ② 寫稿完成後：只有「零 finding」才自動放行。
     * 有 warning 就算已經 acknowledge 過也要人看 —— LLM 剛寫出來的稿不可能有人確認過。
     */
    public function afterScriptGenerated(Product $product): void
    {
        if (! config('video.autopilot.script')
            || $product->status !== ProductStatus::ScriptPendingReview
            || ($product->compliance_report['findings'] ?? []) !== []
            || ProductResource::scriptApprovalBlockers($product) !== []) {
            return;
        }

        $product->transitionTo(ProductStatus::ScriptApproved, self::AUTOPILOT);
        $this->afterScriptApproved($product);
    }

    public function afterScriptApproved(Product $product): void
    {
        GenerateAssetsJob::dispatch($product->id);
    }

    /**
     * 每個素材任務（配音／動畫）結束時呼叫。全部塵埃落定才推進狀態：
     * 有失敗 → AssetsPartial，否則 → AssetsPendingReview（再看能不能自動放行 ③）。
     *
     * 多個 worker 可能同時完成最後兩個鏡頭，用 row lock 確保只有一個人推進狀態，
     * 否則第二個 transitionTo 會撞 IllegalStatusTransition。
     */
    public function assetsSettled(Product $product): void
    {
        $ready = DB::transaction(function () use ($product) {
            $locked = Product::lockForUpdate()->find($product->id);

            if (! $locked || $locked->status !== ProductStatus::AssetsGenerating) {
                return false;
            }

            $tasks = $locked->shots()->get()->flatMap(fn (Shot $shot) => self::assetStatuses($locked, $shot));

            if ($tasks->contains(fn (string $status) => in_array($status, ['pending', 'processing'], true))) {
                return false;
            }

            $locked->transitionTo($tasks->contains('failed') ? ProductStatus::AssetsPartial : ProductStatus::AssetsPendingReview, 'system');

            return $locked->status === ProductStatus::AssetsPendingReview;
        });

        if ($ready) {
            $this->afterAssetsReady($product->refresh());
        }
    }

    /**
     * ③ 素材完成後：沒有 AI 生成的動畫（純商品圖 + Ken Burns + TTS）就沒什麼好看的，
     * 自動放行；有 Kling／Dola 動畫則一定停下來讓人看有沒有變形。
     *
     * ⚠️ 判斷依據是「逐鏡解析後有沒有任何一鏡不是 none」，不是看 product.video_provider ——
     *    鏡頭可以各自覆寫供應商，只看商品那一欄的話，混合模式（商品 none + 某幾鏡 kling）
     *    會讓 AI 動畫不經人眼直接進渲染。
     */
    public function afterAssetsReady(Product $product): void
    {
        if (! config('video.autopilot.assets') || app(VideoGeneratorFactory::class)->hasAiMotion($product)) {
            return;
        }

        $product->transitionTo(ProductStatus::AssetsApproved, self::AUTOPILOT);
        $this->afterAssetsApproved($product);
    }

    public function afterAssetsApproved(Product $product): void
    {
        SubmitRenderJob::dispatch($product->id);
    }

    /**
     * 這個鏡頭有哪些素材任務要等。
     *
     * 配音只在 TTS 模式才算：GenerateScriptJob 在 bgm_only 也會寫配音稿並標 pending，
     * 不排除的話 bgm_only 的商品會永遠卡在 assets_generating。
     *
     * @return list<string>
     */
    private static function assetStatuses(Product $product, Shot $shot): array
    {
        return array_values(array_filter([
            (string) $shot->video_status,
            (string) $product->audio_mode === AudioMode::Tts->value && filled($shot->voiceover_text) ? (string) $shot->voiceover_status : null,
        ]));
    }
}
