<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\CaseStatus;
use App\Models\BuildingCase;
use App\Services\Contracts\ImageGeneratorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $results = $imageGenerator->generateCharacterPreviews(
            $buildingCase->character_dna ?? $buildingCase->name,
            4
        );

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

    public function approveCharacter(BuildingCase $buildingCase, Request $request): JsonResponse
    {
        $validated = $request->validate(['character_option_id' => 'required|uuid']);

        $buildingCase->update(['approved_character_id' => $validated['character_option_id']]);
        $buildingCase->transitionTo(CaseStatus::CharacterApproved, 'operator');

        return response()->json($buildingCase->fresh());
    }
}
