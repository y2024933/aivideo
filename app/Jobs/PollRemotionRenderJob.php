<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CaseStatus;
use App\Models\BuildingCase;
use App\Services\Contracts\VideoEditorContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class PollRemotionRenderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $caseId,
        public readonly string $renderId,
        public readonly int $pollCount = 0,
    ) {}

    public function handle(VideoEditorContract $videoEditor): void
    {
        $case = BuildingCase::find($this->caseId);

        if (! $case || $case->final_video_url) {
            return; // 已完成或已刪除
        }

        // 超過 20 次輪詢視為逾時（20 x 15s = 5 分鐘）
        if ($this->pollCount >= 20) {
            Log::error('[PollRemotionRenderJob::handle] Polling timeout', ['case_id' => $this->caseId]);
            $case->update(['render_id' => null]);
            $case->transitionTo(CaseStatus::ProducingFinal, 'system', '影片渲染逾時');
            return;
        }

        try {
            $result = $videoEditor->queryRenderStatus($this->renderId);
        } catch (\Throwable $e) {
            Log::error('[PollRemotionRenderJob::handle] 查詢失敗', ['exception' => $e]);
            self::dispatch($this->caseId, $this->renderId, $this->pollCount + 1)->delay(now()->addSeconds(15));
            return;
        }

        match ($result['status']) {
            'completed' => $this->handleCompleted($case, $result),
            'failed' => $this->handleFailed($case, $result),
            default => self::dispatch($this->caseId, $this->renderId, $this->pollCount + 1)->delay(now()->addSeconds(15)),
        };
    }

    private function handleCompleted(BuildingCase $case, array $result): void
    {
        $case->update(['final_video_url' => $result['video_url'], 'render_id' => null]);
        $case->transitionTo(CaseStatus::FinalPendingReview, 'system');
    }

    private function handleFailed(BuildingCase $case, array $result): void
    {
        Log::error('[PollRemotionRenderJob::handleFailed] 渲染失敗', [
            'case_id' => $case->id,
            'error' => $result['error'],
        ]);
        $case->update(['render_id' => null]);
        $case->transitionTo(CaseStatus::ProducingFinal, 'system', $result['error']);
    }
}
