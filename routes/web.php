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
    $pages = \App\Models\Page::where('site_id', $site->id)->where('is_published', true)->where('page_type', '!=', 'home')->orderBy('sort_order')->get(['title', 'slug']);
    foreach ($pages as $p) {
        $lines[] = "- {$host}/{$p->slug} — {$p->title}";
    }
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

// 子路由（必須在萬用路由之前）
Route::get('/projects/{slug}', [SiteController::class, 'projectShow'])->name('site.projects.show');
Route::get('/news/{slug}', [SiteController::class, 'newsShow'])->name('site.news.show');
Route::get('/progress/album/{album}', [SiteController::class, 'progressAlbum'])->name('site.progress.album');

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

// 動態頁面（萬用路由，必須放在所有固定路由之後）
Route::post('/{pageSlug}', [SiteController::class, 'dynamicPagePost'])->name('site.page.post')->where('pageSlug', '[a-z0-9\-]+')->middleware('throttle:10,1');
Route::get('/{pageSlug}', [SiteController::class, 'dynamicPage'])->name('site.page')->where('pageSlug', '[a-z0-9\-]+');

Route::prefix('/preview/{site:slug}')->group(function () {
    Route::get('/', [SiteController::class, 'home'])->name('site.preview');

    Route::get('/projects/{slug}', [SiteController::class, 'projectShow'])->name('site.preview.projects.show');
    Route::get('/news/{slug}', [SiteController::class, 'newsShow'])->name('site.preview.news.show');
    Route::get('/progress/album/{album}', [SiteController::class, 'progressAlbum'])->name('site.preview.progress.album');

    Route::post('/{pageSlug}', [SiteController::class, 'dynamicPagePost'])->name('site.preview.page.post')->where('pageSlug', '[a-z0-9\-]+')->middleware('throttle:10,1');
    Route::get('/{pageSlug}', [SiteController::class, 'dynamicPage'])->name('site.preview.page')->where('pageSlug', '[a-z0-9\-]+');
});
