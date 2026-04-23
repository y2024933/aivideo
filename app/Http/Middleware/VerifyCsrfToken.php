<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Session\TokenMismatchException;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /**
     * Token 過期時 redirect 回上一頁，避免顯示 419 白頁
     */
    public function handle($request, Closure $next)
    {
        try {
            return parent::handle($request, $next);
        } catch (TokenMismatchException $e) {
            // AJAX / Livewire 請求：拋出原始例外，讓前端自行處理（Livewire 會顯示 session 過期提示）
            if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
                throw $e;
            }

            return redirect()->back()->withInput(
                $request->except('_token', 'password', 'password_confirmation')
            );
        }
    }
}
