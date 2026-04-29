<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CaseStatusHistory extends Model
{
    protected $table = 'case_status_history';

    protected $guarded = [];

    public function buildingCase(): BelongsTo
    {
        return $this->belongsTo(BuildingCase::class, 'case_id');
    }
}
