<?php

namespace App\Http\Middleware;

use App\Enums\AdminStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();

        if ($admin && $admin->status === AdminStatus::Blocked) {
            Log::warning('Blocked admin access attempt', [
                'admin_id' => $admin->employee_id,
                'email'    => $admin->email,
                'route'    => $request->route()?->getName(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('auth.middleware.admin_blocked'),
            ], 403);
        }

        return $next($request);
    }
}
