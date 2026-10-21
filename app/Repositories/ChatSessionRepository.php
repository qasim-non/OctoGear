<?php

namespace App\Repositories;

use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\User;

class ChatSessionRepository
{
    public function __construct(private ActiveSessionChannelRepository $sessions) {}

    /** Recheck recipients at delivery time; revoked/expired sessions receive nothing. */
    public function channelsFor(Conversation $conversation): array
    {
        $users = User::query()->whereIn('id', [$conversation->customer_id, $conversation->provider_id])
            ->where('status', UserStatus::Unblocked)->pluck('id');

        return $this->sessions->channels(new User, $users->all(), 'private-chat.sessions.');
    }
}
