<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsCustomerOrProvider
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->type, [UserType::Customer, UserType::ServiceProvider], true)) {
            return response()->json([
                'success' => false,
                'message' => __('auth.general.forbidden'),
            ], 403);
        }

        return $next($request);
    }
}
