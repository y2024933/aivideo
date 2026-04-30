<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CaseStatus;
use App\Models\BuildingCase;
use App\Models\CharacterOption;
use App\Services\ImageDownloader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GenerateCharacterPreviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(
        public readonly string $characterOptionId,
        public readonly string $caseId,
        public readonly string $prompt,
    ) {}

    public function handle(): void
    {
        $option = CharacterOption::find($this->characterOptionId);
        if (! $option || $option->status === 'done') {
            return;
        }

        $apiKey = config('services.fal.key');
        if (! $apiKey) {
            $this->markFailed($option, 'FAL_API_KEY not configured');
            return;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Key {$apiKey}",
                'Content-Type' => 'application/json',
            ])
                ->timeout(90)
                ->post('https://fal.run/fal-ai/flux-pro/v1.1', [
                    'prompt' => $this->prompt,
                    'image_size' => 'portrait_16_9',
                    'num_images' => 1,
                ]);

            if (! $response->successful()) {
                $this->markFailed($option, "fal.ai HTTP {$response->status()}");
                return;
            }

            $remoteUrl = $response->json('images.0.url');
            if (! $remoteUrl) {
                $this->markFailed($option, 'fal.ai response missing image URL');
                return;
            }

            $localUrl = ImageDownloader::download($remoteUrl, 'characters');

            $option->update([
                'image_url' => $localUrl,
                'fal_request_id' => $response->json('request_id'),
                'status' => 'done',
                'cost_usd' => config('services.fal.cost_per_image', 0.05),
            ]);

            $case = BuildingCase::find($this->caseId);
            if ($case) {
                $case->addCost((float) config('services.fal.cost_per_image', 0.05));
                $this->checkAllDone($case);
            }

            Log::info('[GenerateCharacterPreviewJob] done', ['option_id' => $this->characterOptionId, 'image_url' => $localUrl]);

        } catch (\Throwable $e) {
            Log::error('[GenerateCharacterPreviewJob] exception', ['error' => $e->getMessage()]);
            $this->markFailed($option, $e->getMessage());
        }
    }

    private function markFailed(CharacterOption $option, string $error): void
    {
        $option->update(['status' => 'failed', 'error_message' => $error]);

        $case = BuildingCase::find($this->caseId);
        if ($case && $case->characterOptions()->where('status', 'pending')->count() === 0) {
            $allFailed = $case->characterOptions()->where('status', 'done')->count() === 0;
            if ($allFailed) {
                $case->transitionTo(CaseStatus::CharacterFailed, 'system', $error);
            }
        }
    }

    private function checkAllDone(BuildingCase $case): void
    {
        $pending = $case->characterOptions()->where('status', 'pending')->count();
        if ($pending === 0) {
            $case->transitionTo(CaseStatus::CharacterPendingReview, 'system');
        }
    }
}
