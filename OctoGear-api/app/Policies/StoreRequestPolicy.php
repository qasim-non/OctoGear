<?php

namespace App\Policies;

use App\Models\StoreRequest;
use App\Models\User;

class StoreRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isProvider();
    }

    public function view(User $user, StoreRequest $storeRequest): bool
    {
        return $user->id === $storeRequest->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isCustomer() || $user->isProvider();
    }

    public function update(User $user, StoreRequest $storeRequest): bool
    {
        return false;
    }

    public function delete(User $user, StoreRequest $storeRequest): bool
    {
        return false;
    }
}
