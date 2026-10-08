<?php

declare(strict_types=1);

namespace App\Services\Video;

use App\Enums\VideoProvider;
use App\Models\Product;
use App\Models\Shot;
use App\Services\Contracts\VideoGeneratorContract;
use App\Services\DolaVideoGenerator;
use App\Services\KlingVideoGenerator;
use App\Services\NullVideoGenerator;
use App\Services\Stubs\StubVideoGenerator;
use Throwable;

/**
 * 動畫 provider 的解析點（與 Llm\ScriptWriterFactory 同一個模式）。
 *
 * 三層優先序：shots.video_provider（null = 繼承）> products.video_provider（null = 繼承）
 * > config('services.video_provider')。所以同一支影片可以混合 —— 幾個鏡頭給 Kling、
 * 其他鏡頭純 Ken Burns。
 *
 * ⚠️ 任何「要不要做動畫」的判斷都必須經過這裡。寫死成 === VideoProvider::Kling 的
 *    判斷會讓 dola／未來的 provider 靜默什麼都不做（operator 以為設定生效了，
 *    但影片永遠不會產生，而且沒有任何錯誤訊息）。
 */
final class VideoGeneratorFactory
{
    public function make(?VideoProvider $provider = null): VideoGeneratorContract
    {
        /*
         * ⚠️ 付費 API 防護網的一部分，不可繞過：use_real_apis 為 false 時，
         *    連「明確指定了 provider」也一律回 stub，否則任何測試只要塞一個
         *    video_provider = kling 就會真的打 Kling（$0.21 一次）。
         *
         * 這裡刻意走容器而不是 new StubVideoGenerator()：測試要能用
         * app()->instance(VideoGeneratorContract::class, $mock) 換掉它。
         * AppServiceProvider 在 use_real_apis = false 時把這個 contract 綁成
         * StubVideoGenerator（不經本 factory），所以不會遞迴。
         */
        if (! config('services.use_real_apis')) {
            return app(VideoGeneratorContract::class);
        }

        return match ($provider ?? $this->default()) {
            VideoProvider::None => new NullVideoGenerator(),
            VideoProvider::Kling => app(KlingVideoGenerator::class),
            VideoProvider::Dola => app(DolaVideoGenerator::class),
            VideoProvider::Stub => new StubVideoGenerator(),
        };
    }

    /**
     * 全域預設。無法辨識（或被設成 stub）時退回 None —— 最安全的那個：
     * 不花錢、不會有 AI 把商品畫歪。
     */
    public function default(): VideoProvider
    {
        return match (VideoProvider::tryFrom((string) config('services.video_provider'))) {
            VideoProvider::Kling => VideoProvider::Kling,
            VideoProvider::Dola => VideoProvider::Dola,
            default => VideoProvider::None,
        };
    }

    /**
     * 目前真的可用的 provider。給 Filament 下拉用 —— 列出設定不齊的選項
     * 只會讓 operator 選了之後整批鏡頭 video_failed。
     *
     * 不含 stub：那不是 operator 該選的東西（在 production 選到假的會以為做完了）。
     *
     * @return array<string, string> provider value => label
     */
    public function availableOptions(): array
    {
        return collect(VideoProvider::selectable())
            ->filter(fn (VideoProvider $provider) => $this->supportsProvider($provider))
            ->mapWithKeys(fn (VideoProvider $provider) => [$provider->value => $provider->getLabel()])
            ->all();
    }

    /** 這個鏡頭實際採用的 provider（三層優先序） */
    public function providerFor(Shot $shot): VideoProvider
    {
        return $this->tryFrom($shot->video_provider)
            ?? $this->tryFrom($shot->product?->video_provider)
            ?? $this->default();
    }

    /** 這個鏡頭該用哪個 generator 實例 */
    public function resolveFor(Shot $shot): VideoGeneratorContract
    {
        return $this->make($this->providerFor($shot));
    }

    /**
     * 這個 provider 的設定齊不齊。
     *
     * ⚠️ 刻意**不**受 use_real_apis 影響：它問的是「operator 的設定能不能跑」，
     *    stub 模式下也要擋得住 dola（未實作）與缺 key 的 kling，否則
     *    「選了 dola 卻靜默什麼都不做」這個 bug 就沒有測試守得住。
     */
    public function supportsProvider(VideoProvider $provider): bool
    {
        return $this->describe($provider)?->supports() ?? false;
    }

    /** USD／秒。provider 建不起來（例如 kling 缺 key）時回 0 —— 它本來也跑不動 */
    public function costPerSecondOf(VideoProvider $provider): float
    {
        return $this->describe($provider)?->costPerSecond() ?? 0.0;
    }

    /**
     * provider 不可用的人話原因，會寫進 shot.video_error 與 product.status_message。
     * 可用時回 null。
     */
    public function unavailableReason(VideoProvider $provider): ?string
    {
        if ($this->supportsProvider($provider)) {
            return null;
        }

        return match ($provider) {
            VideoProvider::Dola => DolaVideoGenerator::REASON,
            VideoProvider::Kling => 'Kling 金鑰未設定（KLING_ACCESS_KEY／KLING_SECRET_KEY），無法送出動畫任務。'
                . '請補上金鑰，或把動畫供應商改成純商品圖 Ken Burns。',
            default => "動畫供應商「{$provider->getLabel()}」目前不可用，請改用其他供應商。",
        };
    }

    /**
     * 這支影片有沒有任何 AI 生成的動畫。
     *
     * checkpoint ③ 的自動放行條件：只要有**任何一個**鏡頭不是 none 就不放行 ——
     * AI 動畫一定要人看過有沒有把商品畫變形（變形的商品圖就是假廣告）。
     */
    public function hasAiMotion(Product $product): bool
    {
        return $product->shots()->get()
            ->contains(fn (Shot $shot) => $this->providerFor($shot->setRelation('product', $product)) !== VideoProvider::None);
    }

    /** 本支影片的 AI 動畫預估成本（USD）：Σ 鏡頭秒數 × 該鏡頭 provider 的每秒單價 */
    public function estimatedCostUsd(Product $product): float
    {
        return (float) $product->shots()->get()->sum(
            fn (Shot $shot) => (float) $shot->duration_seconds
                * $this->costPerSecondOf($this->providerFor($shot->setRelation('product', $product)))
        );
    }

    private function tryFrom(mixed $value): ?VideoProvider
    {
        return filled($value) ? VideoProvider::tryFrom((string) $value) : null;
    }

    /**
     * 只為了問 name()／supports()／costPerSecond() 而建的實例，**不會用來打 API**。
     *
     * 這裡故意不走 make()：make() 在 stub 模式下回 stub，問不到真實 provider 的設定狀態。
     * 建構子缺設定會丟例外（KlingVideoGenerator 就是），接起來當成「不可用」。
     * 所有 generator 的建構子都只讀 config、不連外，建起來是安全的。
     */
    private function describe(VideoProvider $provider): ?VideoGeneratorContract
    {
        try {
            return match ($provider) {
                VideoProvider::None => new NullVideoGenerator(),
                VideoProvider::Kling => new KlingVideoGenerator(),
                VideoProvider::Dola => app(DolaVideoGenerator::class),
                VideoProvider::Stub => new StubVideoGenerator(),
            };
        } catch (Throwable) {
            return null;
        }
    }
}
