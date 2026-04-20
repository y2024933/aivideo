<?php

namespace App\Models;

use App\Traits\OptimizesImages;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory, OptimizesImages;

    public function imageFields(): array { return ['logo_path', 'favicon_path']; }

    protected $fillable = [
        'slug',
        'name',
        'brand_name',
        'theme_key',
        'logo_path',
        'logo_alt',
        'favicon_path',
        'is_active',
        'homepage_sections',
        'hero_content',
        'about_content',
        'service_content',
        'contact_content',
        'social_links',
        'seo_defaults',
        'footer_content',
        'tracking',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'homepage_sections' => 'array',
        'hero_content' => 'array',
        'about_content' => 'array',
        'service_content' => 'array',
        'contact_content' => 'array',
        'social_links' => 'array',
        'seo_defaults' => 'array',
        'footer_content' => 'array',
        'tracking' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function projectStatuses(): HasMany
    {
        return $this->hasMany(ProjectStatus::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(SiteDomain::class);
    }

    public function newsCategories(): HasMany
    {
        return $this->hasMany(NewsCategory::class);
    }

    public function newsArticles(): HasMany
    {
        return $this->hasMany(NewsArticle::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function progressUpdates(): HasMany
    {
        return $this->hasMany(ProgressUpdate::class);
    }

    public function contactMessages(): HasMany
    {
        return $this->hasMany(ContactMessage::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function navigationItems(): HasMany
    {
        return $this->hasMany(NavigationItem::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
