<?php

namespace App\Repositories;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceTokenRepository
{
    public function register(User $user, PersonalAccessToken $session, array $data): void
    {
        // Lock the session before changing its subscription, including during logout.
        PersonalAccessToken::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
        DeviceToken::query()->where('personal_access_token_id', $session->id)
            ->where('token', '!=', $data['token'])->delete();
        DeviceToken::query()->upsert([[
            'token' => $data['token'],
            'user_id' => $user->id,
            'personal_access_token_id' => $session->id,
            'platform' => $data['platform'],
            'locale' => $data['locale'],
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['token'], ['user_id', 'personal_access_token_id', 'platform', 'locale', 'last_seen_at', 'updated_at']);
    }

    public function unregister(PersonalAccessToken $session): void
    {
        PersonalAccessToken::query()->whereKey($session->id)->lockForUpdate()->first();
        DeviceToken::query()->where('personal_access_token_id', $session->id)->delete();
    }

    public function activeFor(User $user): Collection
    {
        return $this->activeQuery($user)->get();
    }

    public function findActive(User $user, int $deviceId, int $sessionId): ?DeviceToken
    {
        return $this->activeQuery($user)->whereKey($deviceId)
            ->where('personal_access_token_id', $sessionId)->first();
    }

    public function removeInvalid(DeviceToken $device): void
    {
        // Do not remove a replacement registered while the FCM request ran.
        DeviceToken::query()->whereKey($device->id)->where('token', $device->token)
            ->where('personal_access_token_id', $device->personal_access_token_id)->delete();
    }

    private function activeQuery(User $user): Builder
    {
        return DeviceToken::query()->where('user_id', $user->id)->where('platform', 'android')
            ->where('last_seen_at', '>=', now()->subDays(config('push.stale_days')))
            ->whereHas('accessToken', function ($query) use ($user) {
                $query->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->id)
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
                if ($minutes = config('sanctum.expiration')) {
                    $query->where('created_at', '>', now()->subMinutes($minutes));
                }
            });
    }
}
