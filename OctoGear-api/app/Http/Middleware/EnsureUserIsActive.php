<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status === UserStatus::Blocked) {
            Log::warning('Blocked user access attempt', [
                'user_id' => $user->id,
                'mobile'  => $user->mobile,
                'route'   => $request->route()?->getName(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('auth.middleware.user_blocked'),
            ], 403);
        }

        return $next($request);
    }
}
