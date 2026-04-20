<?php

namespace App\Http\Middleware;

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
            ->with('site')
            ->where('domain', $host)
            ->first()
            ?->site;

        $request->attributes->set('currentSite', $site);

        return $next($request);
    }
}
