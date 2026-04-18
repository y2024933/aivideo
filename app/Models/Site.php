<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'brand_name',
        'theme_key',
        'primary_color',
        'secondary_color',
        'logo_path',
        'favicon_path',
        'contact_email',
        'contact_phone',
        'is_active',
        'has_news',
        'has_progress',
        'has_contact_form',
        'has_projects',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'has_news' => 'boolean',
        'has_progress' => 'boolean',
        'has_contact_form' => 'boolean',
        'has_projects' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function domains(): HasMany
    {
        return $this->hasMany(SiteDomain::class);
    }

    public function setting(): HasOne
    {
        return $this->hasOne(SiteSetting::class);
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
