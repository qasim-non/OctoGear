<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->type !== UserType::Customer) {
            return response()->json([
                'success' => false,
                'message' => __('auth.middleware.customer_only'),
            ], 403);
        }

        return $next($request);
    }
}
