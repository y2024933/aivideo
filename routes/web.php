<?php

use Illuminate\Support\Facades\Route;

// 根路徑導向 Filament 後台
Route::get('/', fn () => redirect('/admin'));
