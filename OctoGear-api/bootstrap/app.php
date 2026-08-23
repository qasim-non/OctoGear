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
            'auth'         => \App\Http\Middleware\Authenticate::class,
            'locale'       => \App\Http\Middleware\SetLocale::class,
            'user.active'  => \App\Http\Middleware\EnsureUserIsActive::class,
            'admin.active' => \App\Http\Middleware\EnsureAdminIsActive::class,
            'customer'     => \App\Http\Middleware\EnsureIsCustomer::class,
            'provider'     => \App\Http\Middleware\EnsureIsProvider::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            return response()->json([
                'success' => false,
                'message' => __('auth.general.unauthenticated'),
            ], 401);
        });
    })->create();
