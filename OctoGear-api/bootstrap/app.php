<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\EnsureIsCustomer;
use App\Http\Middleware\EnsureIsCustomerOrProvider;
use App\Http\Middleware\EnsureIsProvider;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => Authenticate::class,
            'locale' => SetLocale::class,
            'user.active' => EnsureUserIsActive::class,
            'admin.active' => EnsureAdminIsActive::class,
            'customer' => EnsureIsCustomer::class,
            'provider' => EnsureIsProvider::class,
            'auth.provider' => EnsureIsCustomerOrProvider::class,
        ]);

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => __('auth.general.unauthenticated'),
            ], 401);
        });

        $exceptions->renderable(function (BusinessRuleException $e) {
            $message = $e->messageKey()
                ? __($e->messageKey(), $e->messageParams())
                : $e->getMessage();

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $e->statusCode());
        });
    })->create();
