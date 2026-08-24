<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Store $store): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isProvider();
    }

    public function update(User $user, Store $store): bool
    {
        return $user->id === $store->user_id;
    }

    public function delete(User $user, Store $store): bool
    {
        return $user->id === $store->user_id;
    }

    public function restore(User $user, Store $store): bool
    {
        return $user->id === $store->user_id;
    }

    public function forceDelete(User $user, Store $store): bool
    {
        return $user->id === $store->user_id;
    }
}
