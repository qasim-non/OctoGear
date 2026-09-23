<?php

namespace App\Policies;

use App\Enums\UserType;
use App\Models\CustomerCar;
use App\Models\User;

class CustomerCarPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCustomer();
    }

    public function view(User $user, CustomerCar $customerCar): bool
    {
        return $user->id === $customerCar->customer_id;
    }

    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    public function update(User $user, CustomerCar $customerCar): bool
    {
        return $user->id === $customerCar->customer_id;
    }

    public function delete(User $user, CustomerCar $customerCar): bool
    {
        return $user->id === $customerCar->customer_id;
    }

    public function restore(User $user, CustomerCar $customerCar): bool
    {
        return $user->id === $customerCar->customer_id;
    }

    public function forceDelete(User $user, CustomerCar $customerCar): bool
    {
        return $user->id === $customerCar->customer_id;
    }
}
