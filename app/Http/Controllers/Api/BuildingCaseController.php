<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\CaseStatus;
use App\Jobs\PollKlingVideoJob;
use App\Jobs\PollRemotionRenderJob;
use App\Models\BuildingCase;
use App\Services\Contracts\ImageGeneratorContract;
use App\Services\Contracts\TtsContract;
use App\Services\Contracts\VideoEditorContract;
use App\Services\Contracts\VideoGeneratorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class BuildingCaseController
{
    public function index(): JsonResponse
    {
        return response()->json(
            BuildingCase::orderByDesc('created_at')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'builder_name' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'area_range' => 'nullable|string|max:64',
            'price_range' => 'nullable|string|max:64',
            'target_audience' => 'nullable|string|max:32',
            'tone' => 'nullable|string|max:32',
            'video_length_seconds' => 'nullable|integer|min:15|max:180',
            'character_dna' => 'nullable|string',
            'character_nickname' => 'nullable|string|max:64',
            'story_outline' => 'nullable|string',
            'must_have' => 'nullable|string',
            'taboos' => 'nullable|string',
            'script_v2' => 'nullable|array',
        ]);

        $shotsData = $validated['script_v2']['shots'] ?? [];
        unset($validated['script_v2']);

        $case = BuildingCase::create($validated);

        foreach ($shotsData as $shotData) {
            $case->shots()->create([
                'shot_id' => $shotData['shot_id'] ?? 'S01',
                'shot_order' => $shotData['shot_order'] ?? 1,
                'duration_seconds' => $shotData['duration_seconds'] ?? 5,
                'scene_description' => $shotData['scene_description'] ?? null,
                'voiceover_text' => $shotData['voiceover_text'] ?? null,
                'subtitle' => $shotData['subtitle'] ?? null,
                'emotion' => $shotData['emotion'] ?? null,
                'flux_prompt' => $shotData['flux_prompt'] ?? '',
                'kling_prompt' => $shotData['kling_prompt'] ?? null,
            ]);
        }

        return response()->json($case->load('shots'), 201);
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
                1
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

        $shots = $buildingCase->shots()->whereIn('image_status', ['pending', 'failed'])->get();
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

        $option = $buildingCase->characterOptions()->find($validated['character_option_id']);
        if (! $option) {
            return response()->json(['error' => '角色選項不存在或不屬於此建案'], 422);
        }

        $buildingCase->update(['approved_character_id' => $option->id]);
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

    public function generateVoiceover(BuildingCase $buildingCase, TtsContract $tts): JsonResponse
    {
        $shots = $buildingCase->shots()->orderBy('shot_order')->get();
        $fullText = $shots->pluck('voiceover_text')->filter()->implode("\n");

        if (blank($fullText)) {
            return response()->json(['error' => '此建案無配音稿文字'], 422);
        }

        $voiceName = 'zh-TW-HsiaoChenNeural';

        try {
            $result = $tts->synthesize($fullText, $voiceName);
        } catch (\Throwable $e) {
            Log::error('[BuildingCaseController::generateVoiceover] 配音生成失敗', ['exception' => $e]);

            return response()->json(['error' => '配音生成失敗'], 500);
        }

        $voiceover = $buildingCase->voiceovers()->create([
            'text' => $fullText,
            'voice_id' => $voiceName,
            'audio_url' => $result['audio_url'],
            'duration_seconds' => $result['duration_seconds'],
            'status' => 'done',
        ]);

        return response()->json($voiceover, 201);
    }

    public function renderFinalVideo(BuildingCase $buildingCase, VideoEditorContract $videoEditor): JsonResponse
    {
        // 檢查所有 shots 影片已完成
        $pendingShots = $buildingCase->shots()->where('video_status', '!=', 'done')->count();
        if ($pendingShots > 0) {
            return response()->json(['error' => '尚有未完成的影片片段'], 422);
        }

        // 檢查配音已存在
        if (! $buildingCase->voiceover?->audio_url) {
            return response()->json(['error' => '尚未產生配音'], 422);
        }

        try {
            $result = $videoEditor->submitRender($buildingCase);
        } catch (\Throwable $e) {
            Log::error('[BuildingCaseController::renderFinalVideo] 提交渲染失敗', ['exception' => $e]);
            return response()->json(['error' => '影片渲染提交失敗'], 500);
        }

        $buildingCase->update(['render_id' => $result['render_id']]);

        PollRemotionRenderJob::dispatch($buildingCase->id, $result['render_id'])
            ->delay(now()->addSeconds(30));

        return response()->json([
            'render_id' => $result['render_id'],
            'message' => '影片渲染已提交，請稍後查詢進度',
        ]);
    }
}
