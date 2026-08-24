<?php

namespace App\Policies;

use App\Models\StoreCarComponent;
use App\Models\User;

class StoreCarComponentPolicy
{
    private function isOwner(User $user, StoreCarComponent $component): bool
    {
        return $user->id === $component->store?->user_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, StoreCarComponent $component): bool
    {
        return true;
    }

    public function create(User $user, StoreCarComponent $component): bool
    {
        return $this->isOwner($user, $component);
    }

    public function update(User $user, StoreCarComponent $component): bool
    {
        return $this->isOwner($user, $component);
    }

    public function delete(User $user, StoreCarComponent $component): bool
    {
        return $this->isOwner($user, $component);
    }

    public function restore(User $user, StoreCarComponent $component): bool
    {
        return $this->isOwner($user, $component);
    }

    public function forceDelete(User $user, StoreCarComponent $component): bool
    {
        return $this->isOwner($user, $component);
    }
}
