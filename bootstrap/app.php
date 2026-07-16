<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Di belakang proxy Render (TLS diterminasi di proxy → request ke container jadi
        // HTTP polos + header X-Forwarded-*). Percayai semua proxy agar Laravel membaca
        // X-Forwarded-Proto=https → URL aset/redirect memakai https (bukan http → cegah
        // Mixed Content yang memblokir JS/CSS). Aman: hanya proxy Render yang menjangkau container.
        $middleware->trustProxies(at: '*');

        $middleware->statefulApi();
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Login produk hanya lewat Telegram OTP; arahkan tamu ke sana (bukan route "login" Fortify yang sudah dibuang).
        $middleware->redirectGuestsTo(fn () => route('telegram.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
