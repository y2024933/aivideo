<?php

namespace App\Models;

use App\Traits\CleansTrixContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    use HasFactory, CleansTrixContent;

    protected function trixFields(): array { return ['content']; }

    protected $fillable = [
        'site_id',
        'title',
        'slug',
        'page_type',
        'layout_key',
        'summary',
        'content',
        'cover_image_path',
        'gallery',
        'seo_title',
        'seo_description',
        'is_published',
        'sort_order',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'gallery' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
