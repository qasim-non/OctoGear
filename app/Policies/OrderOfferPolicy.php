<?php

namespace App\Policies;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderOfferPolicy
{
    public function viewConversation(User $user, OrderOffer $offer, Order $order): Response
    {
        if ($offer->order_id !== $order->id) {
            return Response::denyAsNotFound();
        }

        return $user->id === $order->customer_id && $this->view($user, $offer)
            ? Response::allow()
            : Response::deny();
    }

    public function startConversation(User $user, OrderOffer $offer): bool
    {
        return $offer->order?->customer_id === $user->id;
    }

    /**
     * Keep the purchase and competing offers readable after payment and
     * completion. Mutation permissions are checked separately below.
     */
    private function offersAreVisible(Order $order): bool
    {
        return in_array($order->status, [
            OrderStatus::Pending,
            OrderStatus::AwaitingPayment,
            OrderStatus::Paid,
            OrderStatus::Completed,
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
        if (! $this->offersAreVisible($order)) {
            return false;
        }

        // Only the order's customer may list the offer set.
        return $user->id === $order->customer_id;
    }

    public function view(User $user, OrderOffer $offer): bool
    {
        $order = $offer->order;
        if (! $order || ! $this->offersAreVisible($order)) {
            return false;
        }

        return ($user->isProvider() && $this->isProvider($user, $offer))
            || $this->isOrderCustomer($user, $offer);
    }

    public function create(User $user, Order $order): bool
    {
        if (! $user->isProvider()) {
            return false;
        }

        if ($order->order_type !== OrderType::General || $order->status !== OrderStatus::Pending) {
            return false;
        }

        return true;
    }

    public function update(User $user, OrderOffer $offer): bool
    {
        if ($offer->order?->status !== OrderStatus::Pending || $offer->status !== OfferStatus::Pending) {
            return false;
        }

        return $this->isProvider($user, $offer) || $this->isOrderCustomer($user, $offer);
    }

    public function delete(User $user, OrderOffer $offer): bool
    {
        return $offer->order?->status === OrderStatus::Pending
            && $offer->status === OfferStatus::Pending
            && $this->isProvider($user, $offer);
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
