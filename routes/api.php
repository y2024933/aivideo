<?php

use App\Http\Controllers\Api\BuildingCaseController;
use Illuminate\Support\Facades\Route;

Route::prefix('cases')->group(function () {
    Route::post('/', [BuildingCaseController::class, 'store']);
    Route::get('/{buildingCase}', [BuildingCaseController::class, 'show']);
    Route::post('/{buildingCase}/generate-characters', [BuildingCaseController::class, 'generateCharacters']);
    Route::post('/{buildingCase}/approve-character', [BuildingCaseController::class, 'approveCharacter']);
});
