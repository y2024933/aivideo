<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BrowserStrategy;
use App\Enums\BrowserTaskStatus;
use App\Enums\BrowserTaskType;
use App\Models\BrowserTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrowserTask>
 */
final class BrowserTaskFactory extends Factory
{
    protected $model = BrowserTask::class;

    public function definition(): array
    {
        return [
            'type' => BrowserTaskType::ShopeeScrapeProduct,
            'status' => BrowserTaskStatus::Pending,
            'attempts' => 0,
            'max_attempts' => 3,
            'payload' => ['shop_id' => 111, 'item_id' => 222],
            'queued_at' => now(),
        ];
    }

    public function succeeded(): self
    {
        return $this->state(fn () => [
            'status' => BrowserTaskStatus::Succeeded,
            'strategy_used' => BrowserStrategy::Xhr,
            'attempts' => 1,
            'started_at' => now()->subSeconds(8),
            'finished_at' => now(),
            'duration_ms' => 8000,
            'result' => ['title' => '無線藍牙耳機 降噪 長效續航', 'price' => 1290],
        ]);
    }

    /** XHR 失敗、改用 DOM 解析勉強拿到部分欄位 */
    public function degraded(): self
    {
        return $this->succeeded()->state(fn () => [
            'status' => BrowserTaskStatus::Degraded,
            'strategy_used' => BrowserStrategy::Dom,
            'attempts' => 2,
            'error_code' => 'xhr_blocked',
            'error_message' => 'get_pc 回 403，改用 DOM 解析',
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn () => [
            'status' => BrowserTaskStatus::Failed,
            'attempts' => 3,
            'started_at' => now()->subSeconds(20),
            'finished_at' => now(),
            'duration_ms' => 20000,
            'error_code' => 'timeout',
            'error_message' => '等待商品標題選擇器逾時',
            'screenshot_path' => '/storage/browser/failed.png',
        ]);
    }

    public function needsManual(): self
    {
        return $this->failed()->state(fn () => [
            'status' => BrowserTaskStatus::NeedsManual,
            'error_code' => 'captcha',
            'error_message' => '觸發人機驗證，需人工登入',
        ]);
    }
}
