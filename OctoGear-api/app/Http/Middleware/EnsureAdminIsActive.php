<?php

namespace App\Http\Middleware;

use App\Enums\AdminStatus;
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

        if ($admin->status === AdminStatus::Blocked) {
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
