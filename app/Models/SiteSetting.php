<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'homepage_sections',
        'hero_content',
        'about_content',
        'service_content',
        'contact_content',
        'social_links',
        'seo_defaults',
        'footer_content',
    ];

    protected $casts = [
        'homepage_sections' => 'array',
        'hero_content' => 'array',
        'about_content' => 'array',
        'service_content' => 'array',
        'contact_content' => 'array',
        'social_links' => 'array',
        'seo_defaults' => 'array',
        'footer_content' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
