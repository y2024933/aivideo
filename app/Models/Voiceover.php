<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Voiceover extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'duration_seconds' => 'decimal:1',
        'cost_usd' => 'decimal:4',
    ];

    public function buildingCase(): BelongsTo
    {
        return $this->belongsTo(BuildingCase::class, 'case_id');
    }
}
