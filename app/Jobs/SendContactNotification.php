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

        // Email 通知 — 每封獨立，失敗不影響其他通知
        $emails = array_filter($settings['notify_emails'] ?? []);
        $emailFailures = 0;
        $lastError = null;
        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new ContactMessageNotification($this->contactMessage, $this->site));
            } catch (\Throwable $e) {
                $emailFailures++;
                $lastError = $e;
                Log::warning('Email 通知發送失敗', ['email' => $email, 'error' => $e->getMessage()]);
            }
        }

        // LINE 通知 — 每個 target 獨立，失敗不影響其他
        $lineTargets = $this->site->lineTargets()->where('is_active', true)->with('channel')->get();
        $lineService = $lineTargets->isNotEmpty() ? app(LineMessagingService::class) : null;
        $text = $lineTargets->isNotEmpty() ? $this->buildLineMessage() : '';
        $lineAttempts = 0;
        $lineFailures = 0;

        foreach ($lineTargets as $target) {
            if (! $target->channel?->is_active) continue;
            $lineAttempts++;
            try {
                $lineService->pushMessage($target->channel, $target->line_id, $text);
            } catch (\Throwable $e) {
                $lineFailures++;
                $lastError = $e;
                Log::warning('LINE 通知發送失敗', ['target' => $target->line_id, 'error' => $e->getMessage()]);
            }
        }

        // 如果全部通知都失敗，拋例外讓 Job 重試
        $totalAttempts = count($emails) + $lineAttempts;
        $totalFailures = $emailFailures + $lineFailures;
        if ($totalAttempts > 0 && $totalFailures === $totalAttempts && $lastError) {
            throw $lastError;
        }
    }

    private function buildLineMessage(): string
    {
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
        return implode("\n", $lines);
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
