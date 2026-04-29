<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Shot extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'duration_seconds' => 'decimal:1',
        'image_cost_usd' => 'decimal:4',
        'video_cost_usd' => 'decimal:4',
        'is_approved' => 'boolean',
    ];

    public function buildingCase(): BelongsTo
    {
        return $this->belongsTo(BuildingCase::class, 'case_id');
    }

    public function totalCost(): float
    {
        return (float) $this->image_cost_usd + (float) $this->video_cost_usd;
    }
}
