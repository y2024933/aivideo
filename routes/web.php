<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

// Auth routes（走 web middleware，有 session + CSRF）
Route::post('/api/login', [AuthController::class, 'login']);
Route::post('/api/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/api/user', [AuthController::class, 'user'])->middleware('auth:sanctum');

// SPA catch-all（放最後）
Route::get('/{any?}', fn () => view('app'))->where('any', '.*');
