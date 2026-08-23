<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsProvider
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->type !== UserType::ServiceProvider) {
            return response()->json([
                'success' => false,
                'message' => __('auth.middleware.provider_only'),
            ], 403);
        }

        return $next($request);
    }
}
