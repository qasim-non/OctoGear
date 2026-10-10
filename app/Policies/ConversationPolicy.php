<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class ConversationPolicy
{
    public function subscribe(User $user): bool
    {
        return ! $user->isBlocked() && ($user->isCustomer() || $user->isProvider())
            && $user->currentAccessToken() instanceof PersonalAccessToken;
    }

    public function subscribeSession(User $user, mixed $channel): bool
    {
        return $this->subscribe($user)
            && $channel === 'private-chat.sessions.'.$user->currentAccessToken()->id;
    }

    private function isParticipant(User $user, Conversation $conversation): bool
    {
        return $user->id === $conversation->customer_id || $user->id === $conversation->provider_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $this->isParticipant($user, $conversation);
    }

    public function create(User $user): bool
    {
        return $user->isCustomer() || $user->isProvider();
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $this->isParticipant($user, $conversation);
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $this->isParticipant($user, $conversation);
    }

    public function restore(User $user, Conversation $conversation): bool
    {
        return false;
    }

    public function forceDelete(User $user, Conversation $conversation): bool
    {
        return false;
    }
}
