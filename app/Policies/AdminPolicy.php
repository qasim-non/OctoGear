<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\User;

class AdminPolicy
{
    public function permission(User|Admin $actor, string $permission): bool
    {
        return $actor instanceof Admin && $actor->hasPermission($permission);
    }

    public function viewAny(User|Admin $actor): bool
    {
        return $this->permission($actor, 'admins.view');
    }

    public function manage(User|Admin $actor): bool
    {
        return $this->permission($actor, 'admins.manage');
    }
}
