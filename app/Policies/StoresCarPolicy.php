<?php

namespace App\Policies;

use App\Models\StoresCar;
use App\Models\User;

class StoresCarPolicy
{
    private function isOwner(User $user, StoresCar $storesCar): bool
    {
        return $user->id === $storesCar->store?->user_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, StoresCar $storesCar): bool
    {
        return true;
    }

    public function create(User $user, StoresCar $storesCar): bool
    {
        return $this->isOwner($user, $storesCar);
    }

    public function update(User $user, StoresCar $storesCar): bool
    {
        return $this->isOwner($user, $storesCar);
    }

    public function delete(User $user, StoresCar $storesCar): bool
    {
        return $this->isOwner($user, $storesCar);
    }

    public function restore(User $user, StoresCar $storesCar): bool
    {
        return $this->isOwner($user, $storesCar);
    }

    public function forceDelete(User $user, StoresCar $storesCar): bool
    {
        return $this->isOwner($user, $storesCar);
    }
}
