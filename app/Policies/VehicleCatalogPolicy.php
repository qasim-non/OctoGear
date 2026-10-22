<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\User;

class VehicleCatalogPolicy
{
    public function create(User|Admin $actor): bool
    {
        return $actor instanceof Admin && $actor->hasPermission('catalog.manage');
    }

    public function update(User|Admin $actor): bool
    {
        return $this->create($actor);
    }

    public function delete(User|Admin $actor): bool
    {
        return $this->create($actor);
    }

    public function viewAny(User|Admin $actor): bool
    {
        return $actor instanceof Admin && $actor->hasPermission('catalog.view');
    }
}
