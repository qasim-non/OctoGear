<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    private function isCustomer(User $user, Order $order): bool
    {
        return $user->id === $order->customer_id;
    }

    /**
     * A provider can view an order when:
     *  - it's a pending general order (open marketplace browsing), or
     *  - it's a non-pending general order and this provider WON it
     *    (their store is the accepted_store), or
     *  - it's a specific order targeting one of their stores.
     *
     * Once a general order is accepted, losing providers are locked out so they
     * cannot inspect the winning store or the final negotiated price.
     *
     * City scoping is NOT authorization; it is a list-level filter applied in
     * the provider's /orders/general listing.
     */
    private function isProvider(User $user, Order $order): bool
    {
        if ($order->isGeneral()) {
            if ($order->status === OrderStatus::Pending) {
                return true;
            }

            return $user->stores()
                ->where('id', $order->accepted_store_id)
                ->exists();
        }

        $componentStoreUserId = $order->storeCarComponent?->storeCar?->store?->user_id;

        return $order->isSpecific() && $componentStoreUserId === $user->id;
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
