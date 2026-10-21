<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\PersonalAccessToken;

class ActiveSessionChannelRepository
{
    /** Both chat transports exclude revoked and expired Sanctum sessions. */
    public function channels(Model $actor, array $ids, string $prefix): array
    {
        return PersonalAccessToken::query()->where('tokenable_type', $actor->getMorphClass())
            ->whereIn('tokenable_id', $ids)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when(config('sanctum.expiration'), fn ($q, $minutes) => $q->where('created_at', '>', now()->subMinutes($minutes)))
            ->pluck('id')->map(fn ($id) => $prefix.$id)->all();
    }
}
