<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Traefik terminates TLS and forwards to this container over plain
        // HTTP, so without trusting it Laravel reads the scheme as http: form
        // actions and redirects come out as http:// on an https site, and the
        // session cookie loses its Secure flag. APP_URL does not cover this -
        // the URL generator follows the live request, not the configured root.
        // The container is only reachable through the proxy, so '*' is safe.
        $middleware->trustProxies(at: '*');

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->alias([
            'jwt.api' => \App\Http\Middleware\ValidateJWTViaAPI::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
