<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Http\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status === UserStatus::Blocked) {
            Log::warning('Blocked user access attempt', [
                'user_id' => $user->id,
                'mobile' => $user->mobile,
                'route' => $request->route()?->getName(),
            ]);

            return $this->forbidden(__('auth.middleware.user_blocked'));
        }

        return $next($request);
    }
}
