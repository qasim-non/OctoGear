<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RevokeRefreshTokenRequest;
use App\Http\Responses\AuthSessionResponse;
use App\Services\AuthSessionService;
use Illuminate\Http\Request;

class RefreshTokenController extends Controller
{
    public function __construct(private AuthSessionService $sessions, private AuthSessionResponse $responses) {}

    public function refresh(RefreshTokenRequest $request)
    {
        return $this->responses->make($request, $this->sessions->refresh($this->credential($request), $request->validated('request_id'), $this->browser($request)));
    }

    public function logout(RevokeRefreshTokenRequest $request)
    {
        $this->sessions->logoutRefresh($this->credential($request), $this->browser($request));

        return $this->responses->clear($this->success(null, __('auth.logout.success')));
    }

    private function browser(Request $request): bool
    {
        return $request->is('api/auth/admin/*');
    }

    private function credential(Request $request): string
    {
        return (string) ($this->browser($request) ? $request->cookie(config('auth_sessions.cookie_name'), '') : $request->input('refresh_token', ''));
    }
}
