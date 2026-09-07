<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use App\Http\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsCustomer
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->type !== UserType::Customer) {
            return $this->forbidden(__('auth.middleware.customer_only'));
        }

        return $next($request);
    }
}
