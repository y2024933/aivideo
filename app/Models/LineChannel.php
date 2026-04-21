<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LineChannel extends Model
{
    protected $fillable = ['name', 'channel_access_token', 'channel_secret', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'channel_access_token' => 'encrypted',
        'channel_secret' => 'encrypted',
    ];

    protected $appends = ['webhook_url'];

    public function getWebhookUrlAttribute(): string
    {
        return $this->id ? url("/api/line/webhook/{$this->id}") : '';
    }

    public function targets(): HasMany
    {
        return $this->hasMany(LineTarget::class);
    }
}
