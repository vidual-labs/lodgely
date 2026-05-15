<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /** Locales the UI supports. Anything else falls back to app.locale. */
    public const SUPPORTED = ['en', 'de'];

    public function handle(Request $request, Closure $next): Response
    {
        $user   = $request->user();
        $locale = $user?->locale ?? session('locale', config('app.locale'));

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
