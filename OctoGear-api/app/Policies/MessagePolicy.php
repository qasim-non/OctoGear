<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    private function isParticipant(User $user, Message $message): bool
    {
        return $user->id === $message->conversation?->customer_id
            || $user->id === $message->conversation?->provider_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Message $message): bool
    {
        return $this->isParticipant($user, $message);
    }

    public function create(User $user, Message $message): bool
    {
        return $this->isParticipant($user, $message);
    }

    public function update(User $user, Message $message): bool
    {
        return $user->id === $message->sender_id;
    }

    public function delete(User $user, Message $message): bool
    {
        return $user->id === $message->sender_id;
    }

    public function restore(User $user, Message $message): bool
    {
        return false;
    }

    public function forceDelete(User $user, Message $message): bool
    {
        return false;
    }
}
