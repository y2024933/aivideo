<?php

namespace App\Models;

use App\Traits\CleansTrixContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProgressUpdate extends Model
{
    use HasFactory, CleansTrixContent, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->setDescriptionForEvent(fn (string $eventName) => "工程進度「{$this->title}」已{$this->eventLabel($eventName)}");
    }

    private function eventLabel(string $event): string
    {
        return match ($event) { 'created' => '新增', 'updated' => '修改', 'deleted' => '刪除', default => $event };
    }

    protected function trixFields(): array { return ['content']; }

    protected $fillable = [
        'site_id',
        'project_id',
        'title',
        'summary',
        'content',
        'gallery',
        'progress_percent',
        'reported_at',
        'is_published',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'reported_at' => 'date',
        'is_published' => 'boolean',
        'gallery' => 'array',
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
