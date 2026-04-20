<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// SEO：動態 robots.txt 與 sitemap
Route::get('/robots.txt', function (Illuminate\Http\Request $request) {
    $host = $request->getScheme() . '://' . $request->getHost();
    return response(implode("\n", [
        'User-agent: *',
        'Disallow: /admin',
        'Disallow: /preview',
        '',
        "Sitemap: {$host}/sitemap.xml",
        '',
        "# AI 搜尋引擎",
        "# llms.txt: {$host}/llms.txt",
    ]), 200, ['Content-Type' => 'text/plain']);
});
Route::get('/sitemap.xml', SitemapController::class);
Route::get('/llms.txt', function (Illuminate\Http\Request $request) {
    $site = $request->attributes->get('currentSite');
    abort_if(!$site, 404);

    $host = $request->getScheme() . '://' . $request->getHost();
    $desc = $site->seo_defaults['llms_description'] ?? $site->seo_defaults['description'] ?? '';
    $lines = ["# {$site->name}", ''];
    if ($desc) $lines[] = "> {$desc}";
    $lines[] = '';

    // 公司資訊
    $footer = $site->footer_content ?? [];
    if (array_filter([$footer['address'] ?? null, $footer['phone'] ?? null, $footer['email'] ?? null])) {
        $lines[] = '## 公司資訊';
        if ($footer['address'] ?? null) $lines[] = "- 地址：{$footer['address']}";
        if ($footer['phone'] ?? null) $lines[] = "- 電話：{$footer['phone']}";
        if ($footer['email'] ?? null) $lines[] = "- Email：{$footer['email']}";
        if ($footer['area_served'] ?? null) $lines[] = "- 服務區域：{$footer['area_served']}";
        $lines[] = '';
    }

    // 網站內容
    $lines[] = '## 網站內容';
    $lines[] = "- {$host}/ — 首頁";
    $lines[] = "- {$host}/about — 關於我們";
    $lines[] = "- {$host}/projects — 建案作品";
    $lines[] = "- {$host}/news — 最新消息";
    $lines[] = "- {$host}/services — 多元服務";
    $lines[] = "- {$host}/contact — 聯絡我們";
    $lines[] = '';

    // 建案列表
    $projects = $site->projects()->select('name', 'slug', 'summary')->get();
    if ($projects->count()) {
        $lines[] = '## 建案作品';
        foreach ($projects as $p) {
            $line = "- [{$p->name}]({$host}/projects/{$p->slug})";
            if ($p->summary) $line .= "：{$p->summary}";
            $lines[] = $line;
        }
        $lines[] = '';
    }

    return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
});

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
