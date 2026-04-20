<?php

namespace App\Http\Controllers;

use App\Models\NewsArticle;
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

        // 靜態頁面
        foreach (['/' => '1.0', '/about' => '0.8', '/projects' => '0.8', '/news' => '0.8', '/services' => '0.7', '/progress' => '0.7', '/contact' => '0.7'] as $path => $priority) {
            $urls->push(['loc' => $baseUrl . $path, 'changefreq' => 'weekly', 'priority' => $priority]);
        }

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
