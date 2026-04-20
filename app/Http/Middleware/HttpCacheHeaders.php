<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HttpCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 只對 GET 請求加快取標頭
        if (!$request->isMethod('GET')) return $response;

        // 已設定 no-store 的回應（如 captcha）不覆蓋
        $existing = $response->headers->get('Cache-Control', '');
        if (str_contains($existing, 'no-store')) return $response;

        // 後台、preview、登入相關頁面不快取
        if ($request->is('admin/*', 'dashboard*', 'profile*', 'login', 'register', 'captcha*') || $request->is('preview/*')) {
            return $response;
        }

        // 只對前台 site.* 路由加快取
        $routeName = $request->route()?->getName() ?? '';
        if (!str_starts_with($routeName, 'site.')) return $response;

        $staticPages = ['site.home', 'site.about', 'site.services', 'site.projects', 'site.contact'];
        $maxAge = in_array($routeName, $staticPages) ? 3600 : 300;

        $etag = md5($response->getContent());
        $response->headers->set('Cache-Control', "public, max-age={$maxAge}, s-maxage={$maxAge}");
        $response->headers->set('ETag', "\"{$etag}\"");

        if ($request->header('If-None-Match') === "\"{$etag}\"") {
            $response->setStatusCode(304);
            $response->setContent('');
        }

        return $response;
    }
}
