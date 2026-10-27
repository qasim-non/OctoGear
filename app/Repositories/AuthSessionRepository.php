<?php

namespace App\Repositories;

use App\Models\Admin;
use App\Models\AuthSession;
use App\Models\PersonalAccessToken;
use App\Models\RefreshToken;
use App\Models\User;

class AuthSessionRepository
{
    public function forActor(User|Admin $actor)
    {
        return AuthSession::query()->where('actor_type', $actor->getMorphClass())->where('actor_id', $actor->getKey());
    }

    public function revoke(AuthSession $session): void
    {
        $session->forceFill(['revoked_at' => now()])->save();
        PersonalAccessToken::where('auth_session_id', $session->id)->delete();
        RefreshToken::where('auth_session_id', $session->id)->update(['retry_response' => null, 'retry_expires_at' => null]);
    }

    public function prune(): void
    {
        RefreshToken::where('retry_expires_at', '<=', now())->update(['retry_response' => null, 'retry_expires_at' => null]);
        AuthSession::where('expires_at', '<=', now())->orWhere('revoked_at', '<=', now()->subDay())->delete();
        PersonalAccessToken::where('expires_at', '<=', now())->whereNotIn('id', AuthSession::whereNotNull('channel_token_id')->select('channel_token_id'))->delete();
    }
}
