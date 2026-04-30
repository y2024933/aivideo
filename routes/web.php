<?php

use App\Http\Controllers\AssetDownloadController;
use Illuminate\Support\Facades\Route;

// 素材包下載
Route::middleware('auth')->get('/building-cases/{buildingCase}/download-assets', [AssetDownloadController::class, 'download'])->name('building-cases.download-assets');

// 根路徑導向 Filament 後台
Route::get('/', fn () => redirect('/admin'));
