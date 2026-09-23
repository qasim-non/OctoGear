<?php

namespace App\Policies;

use App\Models\Rating;
use App\Models\User;

class RatingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Rating $rating): bool
    {
        if ($user->id === $rating->customer_id) {
            return true;
        }

        return $user->isProvider()
            && $rating->store
            && $rating->store->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    public function update(User $user, Rating $rating): bool
    {
        return $user->id === $rating->customer_id;
    }

    public function delete(User $user, Rating $rating): bool
    {
        return $user->id === $rating->customer_id;
    }

    public function restore(User $user, Rating $rating): bool
    {
        return false;
    }

    public function forceDelete(User $user, Rating $rating): bool
    {
        return false;
    }
}
