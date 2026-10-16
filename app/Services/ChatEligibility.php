<?php

namespace App\Services;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\User;

class ChatEligibility
{
    // Call with repository-loaded relations; these rules perform no explicit queries.
    public function canSend(Conversation $conversation): bool
    {
        return $this->participantsCanSend($conversation->customer, $conversation->provider)
            && (($conversation->offer_id === null && $conversation->order_id === null)
                || $this->storeAllowsChat($conversation->contextStore(), $conversation->provider_id));
    }

    public function canStartProviderOrder(Order $order, User $provider): bool
    {
        return $this->ownsProviderOrder($order, $provider)
            && in_array($order->status, [OrderStatus::AwaitingPayment, OrderStatus::Paid], true)
            && $this->participantsCanSend($order->customer, $provider);
    }

    public function canReconcileProviderOrder(Order $order, User $provider): bool
    {
        // Completion permits recovering an existing chat / receipt, never creation.
        return $order->status === OrderStatus::Completed && $this->ownsProviderOrder($order, $provider);
    }

    private function ownsProviderOrder(Order $order, User $provider): bool
    {
        return $provider->isProvider()
            && (! $order->isGeneral() || ($order->acceptedOffer?->status === OfferStatus::Accepted
                && $order->acceptedOffer->order_id === $order->id))
            && $this->storeAllowsChat($order->fulfillmentStore(), $provider->id);
    }

    public function canStart(OrderOffer $offer): bool
    {
        return $this->participantsCanSend($offer->order?->customer, $offer->store?->owner)
            && $this->storeAllowsChat($offer->store, $offer->store?->user_id);
    }

    public function storeAllowsChat(?Store $store, ?int $providerId): bool
    {
        // Store visibility must not prevent discussing an offer already made.
        return $store !== null && $store->user_id === $providerId;
    }

    private function participantsCanSend(?User $customer, ?User $provider): bool
    {
        return $customer !== null && $provider !== null
            && ! $customer->isBlocked() && ! $provider->isBlocked();
    }
}
