<?php

namespace App\Http\Middleware;

use App\Exceptions\BusinessRuleException;
use Closure;
use Illuminate\Http\Request;

class ProtectBrowserRefresh
{
    public function handle(Request $request, Closure $next)
    {
        $required = $request->is('api/auth/admin/refresh', 'api/auth/admin/logout');
        if ($required || $request->header('X-Auth-Client') === 'web') {
            // A non-simple header and exact trusted Origin prevent cookie CSRF,
            // including login CSRF. No wildcard or suffix-origin matching.
            if ($request->header('X-Auth-Client') !== 'web'
                || ! in_array($request->header('Origin'), config('auth_sessions.browser_origins'), true)) {
                throw new BusinessRuleException(messageKey: 'auth.general.unauthenticated', statusCode: 403);
            }
        }

        return $next($request);
    }
}
