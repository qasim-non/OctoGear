<?php

namespace App\Repositories;

use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class ChatSessionRepository
{
    /** Recheck recipients at delivery time; revoked/expired sessions receive nothing. */
    public function channelsFor(Conversation $conversation): array
    {
        $users = User::query()->whereIn('id', [$conversation->customer_id, $conversation->provider_id])
            ->where('status', UserStatus::Unblocked)->pluck('id');

        return PersonalAccessToken::query()->where('tokenable_type', (new User)->getMorphClass())
            ->whereIn('tokenable_id', $users)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when(config('sanctum.expiration'), fn ($q, $minutes) => $q->where('created_at', '>', now()->subMinutes($minutes)))
            ->pluck('id')->map(fn ($id) => 'private-chat.sessions.'.$id)->all();
    }
}
