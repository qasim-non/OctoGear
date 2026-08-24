<?php

namespace App\Policies;

use App\Models\OrderOffer;
use App\Models\User;

class OrderOfferPolicy
{
    private function isProvider(User $user, OrderOffer $offer): bool
    {
        return $user->id === $offer->store?->user_id;
    }

    private function isOrderCustomer(User $user, OrderOffer $offer): bool
    {
        return $user->id === $offer->order?->customer_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, OrderOffer $offer): bool
    {
        return $this->isProvider($user, $offer) || $this->isOrderCustomer($user, $offer);
    }

    public function create(User $user): bool
    {
        return $user->isProvider();
    }

    public function update(User $user, OrderOffer $offer): bool
    {
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
