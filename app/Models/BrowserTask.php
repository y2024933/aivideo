<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BrowserStrategy;
use App\Enums\BrowserTaskStatus;
use App\Enums\BrowserTaskType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class BrowserTask extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'type' => BrowserTaskType::class,
        'status' => BrowserTaskStatus::class,
        'strategy_used' => BrowserStrategy::class,
        'payload' => 'array',
        'result' => 'array',
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function markRunning(): void
    {
        $this->update([
            'status' => BrowserTaskStatus::Running,
            'started_at' => now(),
            'attempts' => $this->attempts + 1,
        ]);
    }

    /**
     * 卡住的任務：running 超過指定分鐘數仍未結束
     */
    public function scopeStale(Builder $query, int $minutes = 30): Builder
    {
        return $query->where('status', BrowserTaskStatus::Running)
            ->where('started_at', '<', now()->subMinutes($minutes));
    }
}
