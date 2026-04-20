<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectStatus extends Model
{
    use HasFactory;

    protected $fillable = ['site_id', 'name', 'slug', 'sort_order'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
