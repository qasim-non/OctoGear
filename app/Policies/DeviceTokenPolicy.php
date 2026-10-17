<?php

namespace App\Policies;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceTokenPolicy
{
    public function manage(User $user): bool
    {
        return ($user->isCustomer() || $user->isProvider()) && ! $user->isBlocked()
            && $user->currentAccessToken() instanceof PersonalAccessToken;
    }
}
