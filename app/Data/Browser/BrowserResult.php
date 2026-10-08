<?php

declare(strict_types=1);

namespace App\Data\Browser;

use App\Enums\BrowserStrategy;
use App\Enums\BrowserTaskStatus;
use Spatie\LaravelData\Data;

/**
 * 瀏覽器任務的統一回傳。
 *
 * 四種 status 是刻意分開的（而不是 bool success）：
 *   succeeded    → XHR 攔到官方 API 回應，資料可信
 *   degraded     → XHR 沒攔到，從 DOM 撈出來的，欄位可能缺或錯 → 絕不可自動放行 checkpoint
 *   failed       → 技術性失敗（逾時、連不上），可重跑
 *   needs_manual → 人機驗證／登入過期，重跑幾次都一樣，必須人介入
 *
 * ⚠️ 建構子的工廠方法不能叫 succeeded()／degraded()，會跟判斷用的 succeeded() 撞名，
 * 所以工廠一律用 ok()／degrade()／fail()／manual()。
 */
final class BrowserResult extends Data
{
    public const SUCCEEDED = 'succeeded';

    public const DEGRADED = 'degraded';

    public const FAILED = 'failed';

    public const NEEDS_MANUAL = 'needs_manual';

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $artifacts screenshot／trace／har 的路徑
     */
    public function __construct(
        public string $status,
        public ?string $strategy = null,
        public array $data = [],
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public int $durationMs = 0,
        public array $artifacts = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function ok(array $data, ?string $strategy = BrowserStrategy::Xhr->value, int $durationMs = 0, array $artifacts = []): self
    {
        return new self(self::SUCCEEDED, $strategy, $data, null, null, $durationMs, $artifacts);
    }

    /** @param array<string, mixed> $data */
    public static function degrade(array $data, string $errorCode = 'xhr_missed', ?string $errorMessage = null, int $durationMs = 0, array $artifacts = []): self
    {
        return new self(self::DEGRADED, BrowserStrategy::Dom->value, $data, $errorCode, $errorMessage, $durationMs, $artifacts);
    }

    public static function fail(string $errorCode, string $errorMessage, int $durationMs = 0, array $artifacts = []): self
    {
        return new self(self::FAILED, null, [], $errorCode, $errorMessage, $durationMs, $artifacts);
    }

    public static function manual(string $errorCode, string $errorMessage, int $durationMs = 0, array $artifacts = []): self
    {
        return new self(self::NEEDS_MANUAL, null, [], $errorCode, $errorMessage, $durationMs, $artifacts);
    }

    public function succeeded(): bool
    {
        return $this->status === self::SUCCEEDED;
    }

    /** 降級也算拿到資料，但呼叫端必須另外判斷要不要讓人工複核 */
    public function usable(): bool
    {
        return in_array($this->status, [self::SUCCEEDED, self::DEGRADED], true);
    }

    public function isDegraded(): bool
    {
        return $this->status === self::DEGRADED;
    }

    public function needsManual(): bool
    {
        return $this->status === self::NEEDS_MANUAL;
    }

    public function taskStatus(): BrowserTaskStatus
    {
        return BrowserTaskStatus::from($this->status);
    }
}
