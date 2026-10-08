<?php

declare(strict_types=1);

namespace App\Services\Browser;

use App\Data\Browser\BrowserResult;
use App\Enums\BrowserTaskStatus;
use App\Enums\BrowserTaskType;
use App\Models\BrowserTask;
use Illuminate\Database\Eloquent\Model;

/**
 * browser_tasks 的帳本。真實實作與 stub 共用，否則「stub 不寫 task」會讓
 * 所有用 stub 的測試看不到稽核紀錄，正式環境才發現欄位沒填。
 */
final class BrowserTaskRecorder
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array{subject?: Model|null, profile?: string|null, max_attempts?: int}  $options
     */
    public function start(BrowserTaskType $type, array $payload, array $options = []): BrowserTask
    {
        $subject = $options['subject'] ?? null;

        return BrowserTask::create([
            'type' => $type,
            'status' => BrowserTaskStatus::Pending,
            'subject_type' => $subject instanceof Model ? $subject->getMorphClass() : null,
            'subject_id' => $subject instanceof Model ? $subject->getKey() : null,
            'profile' => $options['profile'] ?? null,
            'max_attempts' => (int) ($options['max_attempts'] ?? 3),
            'payload' => $payload,
            'queued_at' => now(),
        ]);
    }

    /**
     * 寫入結果。result 只存 data（原始 payload 體積大，由 products.raw_payload 保管）。
     *
     * @param  array{screenshot?: string|null, trace?: string|null, har?: string|null}  $artifactPaths  已上傳後的 disk key
     */
    public function finish(BrowserTask $task, BrowserResult $result, array $artifactPaths = []): BrowserTask
    {
        $task->update([
            'status' => $result->taskStatus(),
            'strategy_used' => $result->strategy,
            'finished_at' => now(),
            'duration_ms' => $result->durationMs,
            'result' => $result->data,
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage,
            'screenshot_path' => $artifactPaths['screenshot'] ?? null,
            'trace_path' => $artifactPaths['trace'] ?? null,
            'har_path' => $artifactPaths['har'] ?? null,
        ]);

        return $task;
    }
}
