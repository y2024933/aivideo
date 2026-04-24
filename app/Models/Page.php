<?php

namespace App\Models;

use App\Enums\PageTemplate;
use App\Traits\CleansTrixContent;
use App\Traits\OptimizesImages;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    use HasFactory, CleansTrixContent, OptimizesImages;

    public function imageFields(): array { return ['cover_image_path']; }

    protected function trixFields(): array { return ['content']; }

    protected $fillable = [
        'site_id',
        'title',
        'slug',
        'page_type',
        'layout_key',
        'summary',
        'content',
        'faq_items',
        'cover_image_path',
        'cover_image_alt',
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
        'page_type' => PageTemplate::class,
        'faq_items' => 'array',
        'gallery' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
