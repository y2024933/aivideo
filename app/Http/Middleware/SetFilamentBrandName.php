<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

class SetFilamentBrandName
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if ($user && ! $user->isSuperAdmin()) {
            $site = $user->sites()->first();
            if ($site) {
                Filament::getCurrentPanel()->brandName($site->name);
            }
        }

        return $next($request);
    }
}
