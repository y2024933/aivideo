<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Models\SiteDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentSite
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        $site = SiteDomain::query()
            ->with('site.setting')
            ->where('domain', $host)
            ->first()
            ?->site;

        if (! $site && app()->environment(['local', 'testing'])) {
            $site = Site::query()
                ->with('setting')
                ->where('is_active', true)
                ->first();
        }

        $request->attributes->set('currentSite', $site);

        return $next($request);
    }
}
