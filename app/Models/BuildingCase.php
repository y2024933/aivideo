<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CaseStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BuildingCase extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected $casts = [
        'status' => CaseStatus::class,
        'platforms' => 'array',
        'script_v1' => 'array',
        'script_v2' => 'array',
        'reviews' => 'array',
        'cost_usd' => 'decimal:4',
        'completed_at' => 'datetime',
    ];

    public function characterOptions(): HasMany
    {
        return $this->hasMany(CharacterOption::class, 'case_id');
    }

    public function approvedCharacter(): BelongsTo
    {
        return $this->belongsTo(CharacterOption::class, 'approved_character_id');
    }

    public function shots(): HasMany
    {
        return $this->hasMany(Shot::class, 'case_id')->orderBy('shot_order');
    }

    public function voiceover(): HasOne
    {
        return $this->hasOne(Voiceover::class, 'case_id')->latestOfMany();
    }

    public function voiceovers(): HasMany
    {
        return $this->hasMany(Voiceover::class, 'case_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(CaseStatusHistory::class, 'case_id')->orderByDesc('created_at');
    }

    public function transitionTo(CaseStatus $newStatus, string $triggeredBy = 'system', ?string $note = null): void
    {
        $oldStatus = $this->status;
        $this->update(['status' => $newStatus]);

        $this->statusHistory()->create([
            'from_status' => $oldStatus?->value,
            'to_status' => $newStatus->value,
            'triggered_by' => $triggeredBy,
            'note' => $note,
        ]);
    }

    public function addCost(float $amount): void
    {
        $this->increment('cost_usd', $amount);
    }
}
