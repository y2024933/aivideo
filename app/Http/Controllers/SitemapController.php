<?php

namespace App\Http\Controllers;

use App\Models\NewsArticle;
use App\Models\Page;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $site = $request->attributes->get('currentSite');
        abort_if(!$site, 404);

        $baseUrl = $request->getScheme() . '://' . $request->getHost();
        $urls = collect();

        // 首頁
        $urls->push(['loc' => $baseUrl . '/', 'changefreq' => 'weekly', 'priority' => '1.0']);

        // 動態頁面
        Page::where('site_id', $site->id)
            ->where('is_published', true)
            ->where('page_type', '!=', 'home')
            ->select('slug', 'updated_at')
            ->get()
            ->each(fn ($p) => $urls->push([
                'loc' => $baseUrl . '/' . $p->slug,
                'lastmod' => $p->updated_at->toW3cString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ]));

        // 建案
        Project::where('site_id', $site->id)->select('slug', 'updated_at')->get()->each(fn ($p) =>
            $urls->push(['loc' => $baseUrl . '/projects/' . $p->slug, 'lastmod' => $p->updated_at->toW3cString(), 'changefreq' => 'monthly', 'priority' => '0.7'])
        );

        // 新聞
        NewsArticle::where('site_id', $site->id)->where('is_published', true)->select('slug', 'updated_at')->get()->each(fn ($a) =>
            $urls->push(['loc' => $baseUrl . '/news/' . $a->slug, 'lastmod' => $a->updated_at->toW3cString(), 'changefreq' => 'monthly', 'priority' => '0.6'])
        );

        return response(view('sitemap', ['urls' => $urls])->render(), 200, ['Content-Type' => 'application/xml']);
    }
}
