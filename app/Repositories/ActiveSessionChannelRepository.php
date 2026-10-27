<?php

namespace App\Repositories;

use App\Models\PersonalAccessToken;
use Illuminate\Database\Eloquent\Model;

class ActiveSessionChannelRepository
{
    /** Both chat transports exclude revoked and expired Sanctum sessions. */
    public function channels(Model $actor, array $ids, string $prefix): array
    {
        return PersonalAccessToken::query()->where('tokenable_type', $actor->getMorphClass())
            ->whereIn('tokenable_id', $ids)
            ->activeLogin()->with('authSession')->get()
            ->map(fn ($token) => $prefix.$token->channelId())->unique()->values()->all();
    }
}
