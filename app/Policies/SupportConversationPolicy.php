<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\SupportConversation;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class SupportConversationPolicy
{
    public function access(User|Admin $actor): bool
    {
        return $actor instanceof Admin ? $actor->isActive() : ! $actor->isBlocked() && ($actor->isCustomer() || $actor->isProvider());
    }

    public function viewAny(User|Admin $actor): bool
    {
        return $actor instanceof Admin && $this->access($actor);
    }

    public function view(User|Admin $actor, SupportConversation $conversation): bool
    {
        return $this->access($actor) && ($actor instanceof Admin || $conversation->user_id === $actor->id);
    }

    public function subscribeSession(User|Admin $actor, mixed $channel): bool
    {
        $token = $actor->currentAccessToken();

        return $actor instanceof Admin && $this->access($actor) && $token instanceof PersonalAccessToken
            && $channel === 'private-support.admin.sessions.'.$token->id;
    }
}
