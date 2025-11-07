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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,  // この行を追加
            'email2fa' => \App\Http\Middleware\EnsureEmailTwoFactor::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'audit' => \App\Http\Middleware\AuditTrailMiddleware::class,
        ]);

        // API routes は CSRF 検証を除外
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();