<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias untuk dipasang di grup route publik saja (tidak menyentuh panel Filament).
        $middleware->alias([
            'setlocale' => SetLocale::class,
        ]);

        // Guest yang menyentuh route ber-guard `author` diarahkan ke login portal author.
        // (Panel Filament punya redirect sendiri, tidak terpengaruh ini.)
        $middleware->redirectGuestsTo(fn () => route('author.login'));

        // Webhook Midtrans datang dari server gateway (tanpa CSRF token) — keamanan
        // dijamin oleh verifikasi signature di MidtransController, bukan CSRF.
        $middleware->validateCsrfTokens(except: [
            'payment/midtrans/notification',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
