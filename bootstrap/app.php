<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Which proxies to trust for X-Forwarded-* headers. Defaults to '*'
        // (unchanged from before this was configurable) so no existing install
        // changes behaviour; set TRUSTED_PROXIES to your proxy's address or
        // CIDR to make $request->ip() trustworthy — with every proxy trusted
        // the "client IP" is just whatever the caller wrote in the header.
        //
        // env() rather than config() on purpose: this closure runs while the
        // middleware object is being resolved, before the config repository is
        // bound, so config() fatals here. If config is cached (and .env
        // therefore not loaded) this falls back to '*' — i.e. exactly the
        // behaviour that shipped before, never something more permissive.
        //
        // The auth throttles deliberately do not depend on this being set
        // correctly — see AppServiceProvider::bootRateLimiters().
        $trustedProxies = (string) env('TRUSTED_PROXIES', '*');
        $middleware->trustProxies(at: $trustedProxies === '*'
            ? '*'
            : array_values(array_filter(array_map('trim', explode(',', $trustedProxies)))));
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->web(append: [
            // Invalidates every other session for a user when their password
            // changes (profile page, admin reset, password-reset link) — a
            // hijacked or forgotten session doesn't survive a new password.
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \App\Http\Middleware\EnsureUserIsActive::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
