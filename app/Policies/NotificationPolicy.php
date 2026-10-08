<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass()
            && (int) $user->id === (int) $notification->notifiable_id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DatabaseNotification $notification): bool
    {
        return $this->view($user, $notification);
    }

    public function delete(User $user, DatabaseNotification $notification): bool
    {
        return $this->view($user, $notification);
    }

    public function restore(User $user, DatabaseNotification $notification): bool
    {
        return false;
    }

    public function forceDelete(User $user, DatabaseNotification $notification): bool
    {
        return false;
    }
}
