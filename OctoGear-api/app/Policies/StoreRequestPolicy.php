<?php

namespace App\Policies;

use App\Models\StoreRequest;
use App\Models\User;

class StoreRequestPolicy
{
    public function view(User $user, StoreRequest $storeRequest): bool
    {
        return $user->id === $storeRequest->user_id;
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
