<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalAccessToken extends \Laravel\Sanctum\PersonalAccessToken
{
    public function authSession(): BelongsTo
    {
        return $this->belongsTo(AuthSession::class);
    }

    public function channelId(): int
    {
        return (int) ($this->authSession?->channel_token_id ?? $this->id);
    }

    public function scopeActiveLogin(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query->whereHas('authSession', fn ($session) => $session->active())
                ->orWhere(function (Builder $legacy) {
                    $legacy->whereNull('auth_session_id')
                        ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                        ->when(config('sanctum.expiration'), fn ($q, $minutes) => $q->where('created_at', '>', now()->subMinutes($minutes)));
                });
        });
    }
}
