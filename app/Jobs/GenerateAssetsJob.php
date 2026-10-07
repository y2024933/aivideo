<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AudioMode;
use App\Enums\ProductStatus;
use App\Enums\VideoProvider;
use App\Models\Product;
use App\Models\Shot;
use App\Services\Pipeline;
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
 */
final class GenerateAssetsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const ENTRY_STATUSES = [ProductStatus::ScriptApproved, ProductStatus::AssetsPartial];

    public int $tries = 1;

    public function __construct(public readonly string $productId) {}

    public function handle(Pipeline $pipeline): void
    {
        $product = Product::find($this->productId);

        if (! $product || ! in_array($product->status, self::ENTRY_STATUSES, true)) {
            return;
        }

        $product->transitionTo(ProductStatus::AssetsGenerating, 'system');

        $withVoice = (string) $product->audio_mode === AudioMode::Tts->value;
        $withVideo = (string) $product->video_provider === VideoProvider::Kling->value;
        $voiceShots = $videoShots = [];

        foreach ($product->shots()->get() as $shot) {
            /** @var Shot $shot */
            if ($withVoice && filled($shot->voiceover_text) && $shot->voiceover_status !== 'done') {
                $shot->update(['voiceover_status' => 'pending']);
                $voiceShots[] = $shot->id;
            }

            if ($withVideo && $shot->video_status !== 'done') {
                $shot->update(['video_status' => 'pending', 'video_provider' => VideoProvider::Kling->value, 'video_error' => null]);
                $videoShots[] = $shot->id;
            } elseif (! $withVideo && in_array($shot->video_status, ['pending', 'failed'], true)) {
                // operator 重試前把動畫供應商改成 none：舊的失敗紀錄不清掉會永遠卡在 AssetsPartial
                $shot->update(['video_status' => 'skipped']);
            }
        }

        array_map(fn (string $id) => GenerateShotVoiceoverJob::dispatch($id), $voiceShots);
        array_map(fn (string $id) => GenerateShotVideoJob::dispatch($id), $videoShots);

        // 沒有任何任務（純商品圖 + 無配音）時沒人會回報完成，這裡自己推一次；
        // 有任務的話這次呼叫看到 pending 就直接返回。
        $pipeline->assetsSettled($product);
    }
}
