<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use App\Http\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsCustomerOrProvider
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->type, [UserType::Customer, UserType::ServiceProvider], true)) {
            return $this->forbidden(__('auth.general.forbidden'));
        }

        return $next($request);
    }
}
