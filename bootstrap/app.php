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
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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

            $response = [
                'success' => false,
                'message' => $message,
            ];

            if ($e->errors() !== []) {
                $response['errors'] = $e->errors();
            }

            return response()->json($response, $e->statusCode());
        });

        // An API client must never receive a database, filesystem, or stack
        // trace detail, even when APP_DEBUG is enabled for local development.
        // Expected HTTP, validation, authentication, and business outcomes
        // continue through their dedicated renderers below/above this fallback.
        $exceptions->renderable(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface
                || $e instanceof HttpResponseException
                || $e instanceof ValidationException) {
                return null;
            }

            report($e);

            return response()->json([
                'success' => false,
                'message' => __('auth.general.unexpected'),
            ], 500);
        });
    })->create();
