<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'simple-auth' => \App\Http\Middleware\SimpleTokenAuth::class,
            'auth:api' => \App\Http\Middleware\SimpleTokenAuth::class,
            'validate-tenant-auth' => \App\Http\Middleware\ValidateTenantAuth::class,
            'resolve-tenant' => \App\Http\Middleware\ResolveTenant::class,
        ]);

        $middleware->append(\App\Http\Middleware\CorsMiddleware::class);
        
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'webhook/*',
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
