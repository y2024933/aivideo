<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\VideoProvider;
use App\Services\Contracts\VideoGeneratorContract;
use App\Services\Video\DolaAccountPool;
use RuntimeException;

/**
 * Dola AI（dolai.video）的動畫 provider —— 目前只有介面，**沒有任何實作**。
 *
 * Dola 沒有公開 API，只能用瀏覽器自動化操作網頁；而那條路的帳號風險很高
 * （詳見 DolaAccountPool 的 class docblock），所以第一版刻意只留介面：
 * 設定得起來、成本算得出來、選到它會「明確失敗」而不是靜默什麼都不做。
 */
final class DolaVideoGenerator implements VideoGeneratorContract
{
    /** 錯誤訊息前綴，讓 shot.video_error 一眼看得出是「還沒做」而不是「壞了」 */
    public const ERROR_CODE = 'NOT_IMPLEMENTED';

    public const REASON = self::ERROR_CODE . '：Dola 自動化尚未實作，無法送出動畫任務。'
        . '請把動畫供應商改成 Kling（$0.21／5 秒）或純商品圖 Ken Burns（免費）。';

    public function __construct(private readonly DolaAccountPool $accounts) {}

    /** @inheritDoc */
    public function submitImageToVideo(string $imageUrl, string $prompt, int $durationSeconds = 5): array
    {
        throw new RuntimeException(self::REASON);
    }

    /** @inheritDoc */
    public function queryTaskStatus(string $taskId): array
    {
        throw new RuntimeException(self::REASON);
    }

    public function name(): VideoProvider
    {
        return VideoProvider::Dola;
    }

    /**
     * 要有可用的 Dola profile，而且瀏覽器自動化服務本身要設定齊全
     * （base_url 在生產機必須留空，所以生產機上這裡永遠是 false）。
     *
     * ⚠️ 即使這裡回 true，submitImageToVideo() 還是會丟 NOT_IMPLEMENTED ——
     *    supports() 問的是「設定齊不齊」，實作完成之前不要把它當成「可以用了」。
     */
    public function supports(): bool
    {
        return $this->accounts->status()['profiles'] !== []
            && filled(config('services.browser.base_url'))
            && filled(config('services.browser.token'));
    }

    /** Dola 走免費額度，沒有按秒單價；真正的成本是帳號風險（記在 DolaAccountPool） */
    public function costPerSecond(): float
    {
        return 0.0;
    }
}
