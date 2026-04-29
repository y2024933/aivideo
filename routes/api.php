<?php

use App\Http\Controllers\Api\BuildingCaseController;
use Illuminate\Support\Facades\Route;

// 不需認證的路由
Route::get('/health', fn () => response()->json(['status' => 'ok']));

// 需要認證的路由
Route::middleware('auth:sanctum')->prefix('cases')->group(function () {
    Route::get('/', [BuildingCaseController::class, 'index']);
    Route::post('/', [BuildingCaseController::class, 'store']);
    Route::get('/{buildingCase}', [BuildingCaseController::class, 'show']);
    Route::post('/{buildingCase}/generate-characters', [BuildingCaseController::class, 'generateCharacters']);
    Route::post('/{buildingCase}/approve-character', [BuildingCaseController::class, 'approveCharacter']);
    Route::post('/{buildingCase}/generate-scenes', [BuildingCaseController::class, 'generateScenes']);
    Route::post('/{buildingCase}/approve-images', [BuildingCaseController::class, 'approveImages']);
    Route::post('/{buildingCase}/generate-voiceover', [BuildingCaseController::class, 'generateVoiceover']);
    Route::post('/{buildingCase}/render-video', [BuildingCaseController::class, 'renderFinalVideo']);
});
