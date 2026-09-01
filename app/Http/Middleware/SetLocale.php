<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /** Locale yang didukung situs publik. */
    public const SUPPORTED = ['en', 'id'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if (! in_array($locale, self::SUPPORTED, true)) {
            $default = rescue(fn () => siteSettings()->default_locale, 'en', false);
            $locale = in_array($default, self::SUPPORTED, true) ? $default : 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
