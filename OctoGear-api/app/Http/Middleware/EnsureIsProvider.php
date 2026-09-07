<?php

namespace App\Http\Middleware;

use App\Enums\UserType;
use App\Http\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsProvider
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->type !== UserType::ServiceProvider) {
            return $this->forbidden(__('auth.middleware.provider_only'));
        }

        return $next($request);
    }
}
