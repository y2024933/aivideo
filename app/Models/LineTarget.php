<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LineTarget extends Model
{
    protected $fillable = ['line_channel_id', 'type', 'line_id', 'display_name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(LineChannel::class, 'line_channel_id');
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'line_target_site');
    }
}
