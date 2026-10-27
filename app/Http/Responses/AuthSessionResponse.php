<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Cookie;

class AuthSessionResponse
{
    public function make(Request $request, array $data, ?string $message = null): JsonResponse
    {
        $refresh = $data['refresh_token'] ?? null;
        $browser = $request->is('api/auth/admin/*') && $request->header('X-Auth-Client') === 'web';
        if ($browser) {
            unset($data['refresh_token']);
        }
        $response = response()->json(['success' => true, 'message' => $message ?? __('auth.login.success'), 'data' => $data])
            ->header('Cache-Control', 'no-store, private')->header('Pragma', 'no-cache');
        if ($browser && $refresh) {
            $response->withCookie($this->cookie($refresh, Carbon::parse($data['session_expires_at'])));
        }

        return $response;
    }

    public function clear(JsonResponse $response): JsonResponse
    {
        return $response->withCookie($this->cookie('', now()->subYear()))->header('Cache-Control', 'no-store, private');
    }

    private function cookie(string $value, \DateTimeInterface $expires): Cookie
    {
        return Cookie::create(config('auth_sessions.cookie_name'), $value, $expires, '/api/auth/admin', null,
            config('auth_sessions.cookie_secure'), true, false, config('auth_sessions.cookie_same_site'));
    }
}
