<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Admin;
use App\Models\AuthSession;
use App\Models\RefreshToken;
use App\Models\User;
use App\Repositories\AuthSessionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthSessionService
{
    public function __construct(private AuthSessionRepository $sessions) {}

    public function start(User|Admin $actor, bool $browser = false): array
    {
        return DB::transaction(function () use ($actor, $browser) {
            $actor = $actor->newQuery()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            if (! $this->eligible($actor)) {
                throw new BusinessRuleException(messageKey: 'auth.general.unauthenticated', statusCode: 403);
            }
            $session = AuthSession::create([
                'actor_type' => $actor->getMorphClass(), 'actor_id' => $actor->getKey(),
                'browser' => $browser, 'expires_at' => now()->addDays(config('auth_sessions.session_days')),
            ]);

            return $this->issue($actor, $session);
        }, 3);
    }

    public function refresh(string $raw, string $requestId, bool $browser = false): array
    {
        $candidate = $this->findToken($raw);
        $snapshot = $candidate ? AuthSession::find($candidate->auth_session_id) : null;
        if (! $snapshot || $snapshot->browser !== $browser || ($browser && $snapshot->actor_type !== (new Admin)->getMorphClass())) {
            $this->invalid();
        }
        $result = DB::transaction(function () use ($candidate, $snapshot, $requestId) {
            // All credential writes lock the actor, then login, then refresh token.
            $actor = $snapshot->actor()->lockForUpdate()->first();
            $session = AuthSession::whereKey($snapshot->id)->lockForUpdate()->first();
            $token = RefreshToken::whereKey($candidate->id)->lockForUpdate()->first();
            if (! $actor || ! $session || ! $session->isActive() || ! $token || ! $this->eligible($actor)) {
                if ($session) {
                    $this->sessions->revoke($session);
                }

                return null;
            }
            if ($token->used_at) {
                // An exact retry can recover a lost response, never extend the login.
                if ($token->request_id === $requestId && $token->retry_expires_at?->isFuture()
                    && RefreshToken::whereKey($token->replacement_id)->whereNull('used_at')->exists()) {
                    return $token->retry_response;
                }
                $this->sessions->revoke($session);

                return null;
            }
            $response = $this->issue($actor, $session);
            $token->forceFill([
                'used_at' => now(), 'request_id' => $requestId,
                'replacement_id' => explode('.', $response['refresh_token'], 2)[0],
                'retry_response' => $response, 'retry_expires_at' => now()->addSeconds(config('auth_sessions.refresh_retry_seconds')),
            ])->save();

            return $response;
        }, 3);

        // Throw after committing so replay detection cannot roll back revocation.
        return $result ?? $this->invalid();
    }

    public function logout(User|Admin $actor): void
    {
        $token = $actor->currentAccessToken();
        if (! $token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            return;
        }
        DB::transaction(function () use ($actor, $token) {
            $actor->newQuery()->whereKey($actor->getKey())->lockForUpdate()->first();
            $session = $token->auth_session_id ? AuthSession::whereKey($token->auth_session_id)->lockForUpdate()->first() : null;
            if ($session) {
                $this->sessions->revoke($session);
            } else {
                $token->delete();
            }
        }, 3);
    }

    public function logoutRefresh(string $raw, bool $browser): void
    {
        $token = $this->findToken($raw);
        if (! $token) {
            return;
        }
        DB::transaction(function () use ($token, $browser) {
            $snapshot = AuthSession::find($token->auth_session_id);
            if (! $snapshot || $snapshot->browser !== $browser) {
                return;
            }
            $snapshot->actor()->lockForUpdate()->first();
            $session = AuthSession::whereKey($snapshot->id)->lockForUpdate()->first();
            if ($session) {
                $this->sessions->revoke($session);
            }
        }, 3);
    }

    public function revokeAll(User|Admin $actor): void
    {
        DB::transaction(function () use ($actor) {
            $actor->newQuery()->whereKey($actor->getKey())->lockForUpdate()->first();
            foreach ($this->sessions->forActor($actor)->lockForUpdate()->get() as $session) {
                $this->sessions->revoke($session);
            }
            $actor->tokens()->delete();
        }, 3);
    }

    private function issue(User|Admin $actor, AuthSession $session): array
    {
        $expires = now()->addMinutes(config('auth_sessions.access_minutes'))->min($session->expires_at);
        $access = $actor->createToken($actor instanceof Admin ? 'admin-token' : 'auth-token', $actor instanceof Admin ? ['admin'] : ['*'], $expires);
        $access->accessToken->forceFill(['auth_session_id' => $session->id])->save();
        $session->forceFill(['channel_token_id' => $session->channel_token_id ?? $access->accessToken->id, 'last_refreshed_at' => now()])->save();
        $id = (string) Str::uuid();
        $raw = $id.'.'.bin2hex(random_bytes(32));
        RefreshToken::create(['id' => $id, 'auth_session_id' => $session->id, 'token_hash' => hash('sha256', $raw)]);

        return ['token' => $access->plainTextToken, 'token_type' => 'Bearer', 'expires_in' => max(0, $expires->getTimestamp() - now()->getTimestamp()),
            'access_expires_at' => $expires->toIso8601String(), 'refresh_token' => $raw,
            'session_id' => $session->id, 'session_expires_at' => $session->expires_at->toIso8601String()];
    }

    private function findToken(string $raw): ?RefreshToken
    {
        $id = explode('.', $raw, 2)[0];
        $token = Str::isUuid($id) ? RefreshToken::find($id) : null;

        return $token && hash_equals($token->token_hash, hash('sha256', $raw)) ? $token : null;
    }

    private function eligible(User|Admin $actor): bool
    {
        return $actor instanceof Admin ? $actor->isActive() && ! $actor->must_change_password
            : ! $actor->isBlocked() && ($actor->isCustomer() || $actor->isProvider());
    }

    private function invalid(): never
    {
        throw new BusinessRuleException(messageKey: 'auth.general.unauthenticated', statusCode: 401);
    }
}
