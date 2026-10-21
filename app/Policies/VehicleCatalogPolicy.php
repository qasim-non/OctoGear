<?php

namespace App\Policies;

use App\Enums\AdminStatus;
use App\Models\Admin;
use App\Models\User;

class VehicleCatalogPolicy
{
    public function create(User|Admin $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(User|Admin $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function delete(User|Admin $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function viewAny(User|Admin $actor): bool
    {
        return $actor instanceof Admin && $actor->status !== AdminStatus::Blocked;
    }
}
