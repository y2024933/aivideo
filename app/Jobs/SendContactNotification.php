<?php

namespace App\Jobs;

use App\Mail\ContactMessageNotification;
use App\Models\ContactMessage;
use App\Models\Site;
use App\Services\LineMessagingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendContactNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(public ContactMessage $contactMessage, public Site $site) {}

    public function handle(): void
    {
        $settings = $this->site->notification_settings ?? [];
        if (! ($settings['notify_enabled'] ?? false)) return;

        $this->contactMessage->loadMissing('project');

        // Email 通知
        $emails = array_filter($settings['notify_emails'] ?? []);
        if ($emails) {
            Mail::to($emails)->send(new ContactMessageNotification($this->contactMessage, $this->site));
        }

        // LINE 通知
        $lineTargets = $this->site->lineTargets()->where('is_active', true)->with('channel')->get();
        if ($lineTargets->isEmpty()) return;

        $lineService = app(LineMessagingService::class);
        $cm = $this->contactMessage;
        $lines = ["[{$this->site->name}] 新的聯絡訊息"];
        $lines[] = "姓名：{$cm->name}";
        $lines[] = "電話：{$cm->phone}";
        $lines[] = "電子郵件：{$cm->email}";
        $lines[] = "留言類別：{$cm->inquiry_type}";
        $lines[] = "建案名稱：" . ($cm->project?->name ?? '');
        if ($cm->line_id) $lines[] = "LINE ID：{$cm->line_id}";
        if ($cm->contact_time) $lines[] = "方便時間：{$cm->contact_time}";
        $lines[] = "訊息：" . Str::limit($cm->message, 500);
        $text = implode("\n", $lines);

        foreach ($lineTargets as $target) {
            if ($target->channel?->is_active) {
                $lineService->pushMessage($target->channel, $target->line_id, $text);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('聯絡表單通知 Job 失敗', [
            'contact_message_id' => $this->contactMessage->id,
            'site_id' => $this->site->id,
            'error' => $e->getMessage(),
        ]);
    }
}
