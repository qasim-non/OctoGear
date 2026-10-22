<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EnsureAdminPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        Gate::authorize('permission', [Admin::class, $permission]);

        return $next($request);
    }
}
