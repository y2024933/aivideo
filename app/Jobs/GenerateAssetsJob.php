<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AudioMode;
use App\Enums\ProductStatus;
use App\Enums\VideoProvider;
use App\Models\Product;
use App\Models\Shot;
use App\Services\Pipeline;
use App\Services\Video\VideoGeneratorFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * P5/P6 素材派工：腳本核准（或部分失敗重試）後，替每個鏡頭排配音與 B-roll 任務。
 *
 * ⚠️ 一定要先把「所有」要做的鏡頭標成 pending 再開始派工。sync queue 或 worker 很快時，
 * 第一個任務完成就會呼叫 Pipeline::assetsSettled()，若其他鏡頭還沒標 pending，
 * 會被誤判成「全部完成」而提早推進狀態。
 *
 * 已經 done 的鏡頭不重做 —— 部分失敗重試時只補失敗的，不重付成功那幾鏡的錢。
 *
 * ⚠️ 動畫供應商一律經 VideoGeneratorFactory 逐鏡解析，**不可以**寫死成
 *    「=== kling 才做」。寫死的版本在 video_provider = dola 時判斷為 false，
 *    結果系統靜默什麼都不做：operator 以為設定生效了，但影片永遠不會產生，
 *    而且沒有任何錯誤訊息。現在的規則是：
 *      none       → video_status = skipped（不是 failed），正常往下走
 *      不可用     → 明確 failed，原因寫進 shot.video_error 與 product.status_message
 *      可用       → pending + 派工
 */
final class GenerateAssetsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const ENTRY_STATUSES = [ProductStatus::ScriptApproved, ProductStatus::AssetsPartial];

    public int $tries = 1;

    public function __construct(public readonly string $productId) {}

    public function handle(Pipeline $pipeline, VideoGeneratorFactory $videoFactory): void
    {
        $product = Product::find($this->productId);

        if (! $product || ! in_array($product->status, self::ENTRY_STATUSES, true)) {
            return;
        }

        $product->transitionTo(ProductStatus::AssetsGenerating, 'system');

        $withVoice = (string) $product->audio_mode === AudioMode::Tts->value;
        $voiceShots = $videoShots = [];
        $blockers = [];

        foreach ($product->shots()->get() as $shot) {
            /** @var Shot $shot */
            if ($withVoice && filled($shot->voiceover_text) && $shot->voiceover_status !== 'done') {
                $shot->update(['voiceover_status' => 'pending']);
                $voiceShots[] = $shot->id;
            }

            // setRelation 省掉逐鏡再查一次商品；shot.video_provider 為 null 時要靠它繼承
            $provider = $videoFactory->providerFor($shot->setRelation('product', $product));

            // ⚠️ 不回寫 shot.video_provider：那一欄是 operator 的覆寫（null = 繼承），
            //    派工時釘成實際值的話，之後把商品改回 none 也關不掉這幾鏡的動畫。
            if ($provider === VideoProvider::None) {
                // 純 Ken Burns 不是失敗。順手清掉上一輪的 pending/failed，
                // 否則 operator 把供應商改回 none 後會永遠卡在 AssetsPartial。
                if ($shot->video_status !== 'done') {
                    $shot->update(['video_status' => 'skipped', 'video_error' => null]);
                }

                continue;
            }

            if ($shot->video_status === 'done') {
                continue;
            }

            if ($reason = $videoFactory->unavailableReason($provider)) {
                $shot->update(['video_status' => 'failed', 'video_error' => $reason]);
                $blockers[$reason] = true;   // key 去重：3 個鏡頭同一個原因只講一次

                continue;
            }

            $shot->update(['video_status' => 'pending', 'video_error' => null]);
            $videoShots[] = $shot->id;
        }

        if ($blockers !== []) {
            $product->update(['status_message' => implode(' ', array_keys($blockers))]);
        }

        array_map(fn (string $id) => GenerateShotVoiceoverJob::dispatch($id), $voiceShots);
        array_map(fn (string $id) => GenerateShotVideoJob::dispatch($id), $videoShots);

        // 沒有任何任務（純商品圖 + 無配音）時沒人會回報完成，這裡自己推一次；
        // 有任務的話這次呼叫看到 pending 就直接返回。
        $pipeline->assetsSettled($product);
    }
}
