<?php

namespace App\Http\Middleware;

use App\Http\Traits\ApiResponse;
use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIsActive
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();

        if (! $admin instanceof Admin) {
            return $this->forbidden(__('auth.middleware.admin_required'));
        }

        if (! $admin->isActive() || $admin->must_change_password) {
            Log::warning('Blocked admin access attempt', [
                'admin_id' => $admin->employee_id,
                'email' => $admin->email,
                'route' => $request->route()?->getName(),
            ]);

            return $this->forbidden(__('auth.middleware.admin_blocked'));
        }

        return $next($request);
    }
}
