<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ContactMessage extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->setDescriptionForEvent(fn (string $eventName) => "聯絡訊息（{$this->name}）已{$this->eventLabel($eventName)}");
    }

    private function eventLabel(string $event): string
    {
        return match ($event) { 'created' => '新增', 'updated' => '修改', 'deleted' => '刪除', default => $event };
    }

    protected $fillable = [
        'site_id',
        'project_id',
        'inquiry_type',
        'name',
        'email',
        'phone',
        'line_id',
        'contact_time',
        'message',
        'status',
        'source_page',
        'assigned_to',
        'processed_at',
        'notes',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
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
