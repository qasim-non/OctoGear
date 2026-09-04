<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;

class OrderOfferPolicy
{
    /**
     * Offers only matter while the order is still being decided
     * (pending or negotiating). Once the order is paid, completed,
     * cancelled or rejected, the offer window is closed and offers must
     * not be exposed or mutated.
     */
    private function offersAreVisible(Order $order): bool
    {
        return in_array($order->status, [
            OrderStatus::Pending,
            OrderStatus::Negotiating,
        ], true);
    }

    private function isProvider(User $user, OrderOffer $offer): bool
    {
        return $user->id === $offer->store?->user_id;
    }

    private function isOrderCustomer(User $user, OrderOffer $offer): bool
    {
        return $user->id === $offer->order?->customer_id;
    }

    public function viewAny(User $user, Order $order): bool
    {
        return $this->offersAreVisible($order);
    }

    public function view(User $user, OrderOffer $offer): bool
    {
        if (! $this->offersAreVisible($offer->order)) {
            return false;
        }

        return $this->isProvider($user, $offer) || $this->isOrderCustomer($user, $offer);
    }

    public function create(User $user, Order $order): bool
    {
        if (! $user->isProvider()) {
            return false;
        }

        if ($order->order_type->value !== 'general' || $order->status !== OrderStatus::Pending) {
            return false;
        }

        return true;
    }

    public function update(User $user, OrderOffer $offer): bool
    {
        if (! $this->offersAreVisible($offer->order)) {
            return false;
        }

        return $this->isProvider($user, $offer) || $this->isOrderCustomer($user, $offer);
    }

    public function delete(User $user, OrderOffer $offer): bool
    {
        return $this->isProvider($user, $offer);
    }

    public function restore(User $user, OrderOffer $offer): bool
    {
        return false;
    }

    public function forceDelete(User $user, OrderOffer $offer): bool
    {
        return false;
    }
}
