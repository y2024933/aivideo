<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProgressAlbum extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->setDescriptionForEvent(fn (string $eventName) => "工程相簿已{$this->eventLabel($eventName)}");
    }

    private function eventLabel(string $event): string
    {
        return match ($event) { 'created' => '新增', 'updated' => '修改', 'deleted' => '刪除', default => $event };
    }

    protected $fillable = [
        'site_id',
        'project_id',
        'reported_at',
        'progress_percent',
        'gallery',
        'is_published',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'gallery' => 'array',
        'is_published' => 'boolean',
        'reported_at' => 'date',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
