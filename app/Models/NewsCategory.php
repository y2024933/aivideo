<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsCategory extends Model
{
    use HasFactory;

    protected $fillable = ['site_id', 'name', 'slug', 'sort_order'];

    public function site(): BelongsTo { return $this->belongsTo(Site::class); }
    public function newsArticles(): HasMany { return $this->hasMany(NewsArticle::class); }
}
