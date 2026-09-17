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
        $middleware->redirectGuestsTo(fn () => route('filament.author.auth.login'));
        $middleware->redirectUsersTo(fn () => route('filament.author.pages.author-dashboard'));

        // Webhook BorderPay datang dari server gateway (tanpa CSRF token). Keamanan
        // di sini BUKAN dari CSRF, dan bukan pula dari isi kirimannya: lihat
        // BorderpayService, yang selalu menanyakan ulang statusnya ke gateway.
        $middleware->validateCsrfTokens(except: [
            'payment/borderpay/notification',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
