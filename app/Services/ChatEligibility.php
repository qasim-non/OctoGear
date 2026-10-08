<?php

namespace App\Services;

use App\Enums\StoreStatus;
use App\Models\Conversation;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\User;

class ChatEligibility
{
    // Call with repository-loaded relations; these rules perform no explicit queries.
    public function canSend(Conversation $conversation): bool
    {
        return $this->participantsCanSend($conversation->customer, $conversation->provider)
            && ($conversation->offer_id === null
                || $this->storeAllowsChat($conversation->offer?->store, $conversation->provider_id));
    }

    public function canStart(OrderOffer $offer): bool
    {
        return $this->participantsCanSend($offer->order?->customer, $offer->store?->owner)
            && $this->storeAllowsChat($offer->store, $offer->store?->user_id);
    }

    public function storeAllowsChat(?Store $store, ?int $providerId): bool
    {
        return $store !== null && $store->status === StoreStatus::Active
            && $store->user_id === $providerId;
    }

    private function participantsCanSend(?User $customer, ?User $provider): bool
    {
        return $customer !== null && $provider !== null
            && ! $customer->isBlocked() && ! $provider->isBlocked();
    }
}
