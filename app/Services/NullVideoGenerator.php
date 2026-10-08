<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\VideoProvider;
use App\Exceptions\UnsupportedOperationException;
use App\Services\Contracts\VideoGeneratorContract;

/**
 * 「不做 AI 動畫」的實作（VideoProvider::None）。
 *
 * 存在的意義是讓 none 也是一個正常的 provider，而不是 factory 裡的 null ——
 * 呼叫端不用寫 if (provider !== none)，照 supports()／costPerSecond() 問就好。
 *
 * 任務提交／查詢一律丟 UnsupportedOperationException：走到這裡代表派工邏輯沒有
 * 先擋掉 none，必須吵，不能靜默回假的 task_id（那會讓鏡頭永遠卡在 processing）。
 */
final class NullVideoGenerator implements VideoGeneratorContract
{
    private const REASON = '目前是純商品圖 Ken Burns 模式（動畫供應商 = none），不產生 AI 動畫，'
        . '因此沒有圖轉影片任務可以提交或查詢。要 AI 動畫請把動畫供應商改成 Kling。';

    /** @inheritDoc */
    public function submitImageToVideo(string $imageUrl, string $prompt, int $durationSeconds = 5): array
    {
        throw new UnsupportedOperationException(self::REASON);
    }

    /** @inheritDoc */
    public function queryTaskStatus(string $taskId): array
    {
        throw new UnsupportedOperationException(self::REASON);
    }

    public function name(): VideoProvider
    {
        return VideoProvider::None;
    }

    /** 永遠可用：不需要任何金鑰或外部服務，這是系統的安全預設 */
    public function supports(): bool
    {
        return true;
    }

    public function costPerSecond(): float
    {
        return 0.0;
    }
}
