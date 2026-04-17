<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [SiteController::class, 'home'])->name('site.home');
Route::get('/about', [SiteController::class, 'about'])->name('site.about');
Route::get('/projects', [SiteController::class, 'projects'])->name('site.projects');
Route::get('/projects/{slug}', [SiteController::class, 'projectShow'])->name('site.projects.show');
Route::get('/news', [SiteController::class, 'news'])->name('site.news');
Route::get('/news/{slug}', [SiteController::class, 'newsShow'])->name('site.news.show');
Route::get('/services', [SiteController::class, 'services'])->name('site.services');
Route::get('/progress', [SiteController::class, 'progress'])->name('site.progress');
Route::post('/progress', [SiteController::class, 'progressAuth'])->name('site.progress.auth')->middleware('throttle:10,1');
Route::get('/progress/album/{album}', [SiteController::class, 'progressAlbum'])->name('site.progress.album');
Route::get('/contact', [SiteController::class, 'contact'])->name('site.contact');
Route::post('/contact', [SiteController::class, 'submitContact'])->name('site.contact.submit')->middleware('throttle:30,1');

Route::prefix('/preview/{site:slug}')->group(function () {
    Route::get('/', [SiteController::class, 'home'])->name('site.preview');
    Route::get('/about', [SiteController::class, 'about'])->name('site.preview.about');
    Route::get('/projects', [SiteController::class, 'projects'])->name('site.preview.projects');
    Route::get('/projects/{slug}', [SiteController::class, 'projectShow'])->name('site.preview.projects.show');
    Route::get('/news', [SiteController::class, 'news'])->name('site.preview.news');
    Route::get('/news/{slug}', [SiteController::class, 'newsShow'])->name('site.preview.news.show');
    Route::get('/services', [SiteController::class, 'services'])->name('site.preview.services');
    Route::get('/progress', [SiteController::class, 'progress'])->name('site.preview.progress');
    Route::post('/progress', [SiteController::class, 'progressAuth'])->name('site.preview.progress.auth')->middleware('throttle:10,1');
    Route::get('/progress/album/{album}', [SiteController::class, 'progressAlbum'])->name('site.preview.progress.album');
    Route::get('/contact', [SiteController::class, 'contact'])->name('site.preview.contact');
    Route::post('/contact', [SiteController::class, 'submitContact'])->name('site.preview.contact.submit');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/captcha', [\App\Http\Controllers\CaptchaController::class, 'generate'])->name('captcha.generate')->middleware('throttle:30,1');

require __DIR__.'/auth.php';
