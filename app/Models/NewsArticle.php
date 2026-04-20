<?php

namespace App\Models;

use App\Traits\CleansTrixContent;
use App\Traits\OptimizesImages;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class NewsArticle extends Model
{
    use HasFactory, CleansTrixContent, LogsActivity, OptimizesImages;

    public function imageFields(): array { return ['featured_image_path']; }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->setDescriptionForEvent(fn (string $eventName) => "消息「{$this->title}」已{$this->eventLabel($eventName)}");
    }

    private function eventLabel(string $event): string
    {
        return match ($event) { 'created' => '新增', 'updated' => '修改', 'deleted' => '刪除', default => $event };
    }

    protected function trixFields(): array { return ['content']; }

    protected $fillable = [
        'site_id',
        'title',
        'slug',
        'news_category_id',
        'summary',
        'content',
        'featured_image_path',
        'featured_image_alt',
        'published_at',
        'is_published',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_published' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function newsCategory(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class);
    }
}
