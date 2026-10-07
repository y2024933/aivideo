<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Voiceover extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'duration_seconds' => 'decimal:1',
        'cost_usd' => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function shot(): BelongsTo
    {
        return $this->belongsTo(Shot::class, 'shot_uuid');
    }
}
