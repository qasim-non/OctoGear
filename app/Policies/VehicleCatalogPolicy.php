<?php

namespace App\Policies;

use App\Enums\AdminStatus;
use App\Models\Admin;
use App\Models\User;

class VehicleCatalogPolicy
{
    public function viewAny(User|Admin $actor): bool
    {
        return $actor instanceof Admin && $actor->status !== AdminStatus::Blocked;
    }
}
