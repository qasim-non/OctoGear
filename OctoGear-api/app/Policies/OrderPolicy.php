<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    private function isCustomer(User $user, Order $order): bool
    {
        return $user->id === $order->customer_id;
    }

    private function isProvider(User $user, Order $order): bool
    {
        return $user->id === $order->store?->user_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->isCustomer() || $user->isProvider();
    }

    public function view(User $user, Order $order): bool
    {
        return $this->isCustomer($user, $order) || $this->isProvider($user, $order);
    }

    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    public function update(User $user, Order $order): bool
    {
        return $this->isCustomer($user, $order);
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }

    public function restore(User $user, Order $order): bool
    {
        return false;
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return false;
    }
}
