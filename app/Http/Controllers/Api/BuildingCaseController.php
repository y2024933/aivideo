<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\CaseStatus;
use App\Jobs\PollKlingVideoJob;
use App\Models\BuildingCase;
use App\Services\Contracts\ImageGeneratorContract;
use App\Services\Contracts\VideoGeneratorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class BuildingCaseController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'builder_name' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'character_dna' => 'nullable|string',
            'target_audience' => 'nullable|string|max:32',
            'tone' => 'nullable|string|max:32',
            'story_outline' => 'nullable|string',
            'must_have' => 'nullable|string',
            'taboos' => 'nullable|string',
            'script_v2' => 'nullable|array',
        ]);

        $case = BuildingCase::create($validated);

        return response()->json($case, 201);
    }

    public function show(BuildingCase $buildingCase): JsonResponse
    {
        return response()->json(
            $buildingCase->load(['characterOptions', 'shots', 'voiceover'])
        );
    }

    public function generateCharacters(BuildingCase $buildingCase, ImageGeneratorContract $imageGenerator): JsonResponse
    {
        $buildingCase->transitionTo(CaseStatus::CharacterGenerating, 'operator');

        try {
            $results = $imageGenerator->generateCharacterPreviews(
                $buildingCase->character_dna ?? $buildingCase->name,
                4
            );
        } catch (\Throwable $e) {
            Log::error('[BuildingCaseController::generateCharacters] 角色生成失敗', ['exception' => $e]);
            $buildingCase->transitionTo(CaseStatus::CharacterFailed, 'system', $e->getMessage());

            return response()->json(['error' => '角色生成失敗'], 500);
        }

        foreach ($results as $result) {
            $buildingCase->characterOptions()->create([
                'prompt' => $buildingCase->character_dna ?? '',
                'image_url' => $result['image_url'],
                'fal_request_id' => $result['request_id'],
                'status' => $result['image_url'] ? 'done' : 'pending',
            ]);
        }

        $buildingCase->transitionTo(CaseStatus::CharacterPendingReview, 'system');

        return response()->json($buildingCase->load('characterOptions'));
    }

    public function generateScenes(BuildingCase $buildingCase, ImageGeneratorContract $imageGenerator): JsonResponse
    {
        $approvedCharacter = $buildingCase->approvedCharacter;

        if (! $approvedCharacter?->image_url) {
            return response()->json(['error' => '尚未核准角色或角色無圖片'], 422);
        }

        $buildingCase->transitionTo(CaseStatus::ImagesGenerating, 'operator');

        $shots = $buildingCase->shots()->where('image_status', 'pending')->get();
        $costPerImage = (float) config('services.fal.cost_per_image');
        $totalCost = 0.0;
        $hasFailure = false;

        foreach ($shots as $shot) {
            try {
                $result = $imageGenerator->generateSceneImage(
                    $shot->flux_prompt,
                    $approvedCharacter->image_url
                );

                $shot->update([
                    'image_url' => $result['image_url'],
                    'image_request_id' => $result['request_id'],
                    'image_status' => $result['image_url'] ? 'done' : 'failed',
                    'image_cost_usd' => $costPerImage,
                ]);

                $totalCost += $costPerImage;
            } catch (\Throwable $e) {
                Log::error('[BuildingCaseController::generateScenes] 場景圖生成失敗', [
                    'shot_id' => $shot->shot_id,
                    'exception' => $e,
                ]);

                $shot->update([
                    'image_status' => 'failed',
                    'image_retry_count' => $shot->image_retry_count + 1,
                ]);
                $hasFailure = true;
            }
        }

        if ($totalCost > 0) {
            $buildingCase->addCost($totalCost);
        }

        $newStatus = $hasFailure ? CaseStatus::ImagesPartial : CaseStatus::ImagesPendingReview;
        $buildingCase->transitionTo($newStatus, 'system');

        return response()->json($buildingCase->load('shots'));
    }

    public function approveCharacter(BuildingCase $buildingCase, Request $request): JsonResponse
    {
        $validated = $request->validate(['character_option_id' => 'required|uuid']);

        $buildingCase->update(['approved_character_id' => $validated['character_option_id']]);
        $buildingCase->transitionTo(CaseStatus::CharacterApproved, 'operator');

        return response()->json($buildingCase->fresh());
    }

    public function approveImages(BuildingCase $buildingCase, VideoGeneratorContract $videoGenerator): JsonResponse
    {
        $buildingCase->transitionTo(CaseStatus::ImagesApproved, 'operator');

        $shots = $buildingCase->shots()->where('image_status', 'done')->get();

        foreach ($shots as $shot) {
            try {
                $result = $videoGenerator->submitImageToVideo(
                    $shot->image_url,
                    $shot->kling_prompt ?? $shot->flux_prompt,
                    (int) ($shot->duration_seconds ?: 5),
                );

                $shot->update([
                    'video_request_id' => $result['task_id'],
                    'video_status' => 'processing',
                ]);

                PollKlingVideoJob::dispatch($shot->id, $result['task_id'])->delay(now()->addSeconds(15));
            } catch (\Throwable $e) {
                Log::error('[BuildingCaseController::approveImages] 影片提交失敗', [
                    'shot_id' => $shot->shot_id,
                    'exception' => $e,
                ]);

                $shot->update([
                    'video_status' => 'failed',
                    'video_error' => $e->getMessage(),
                ]);
            }
        }

        $buildingCase->transitionTo(CaseStatus::ProducingFinal, 'system');

        return response()->json($buildingCase->load('shots'));
    }
}
