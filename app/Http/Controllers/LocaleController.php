<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (in_array($locale, SetLocale::SUPPORTED, true)) {
            $request->session()->put('locale', $locale);
        }

        $previous = url()->previous();
        if (parse_url($previous, PHP_URL_HOST) !== $request->getHost()) {
            $previous = route('home');
        }
        $parts = parse_url($previous);
        parse_str($parts['query'] ?? '', $query);
        $query['lang'] = in_array($locale, SetLocale::SUPPORTED, true) ? $locale : 'en';

        return redirect()->to(($parts['path'] ?? '/').'?'.http_build_query($query));
    }
}
