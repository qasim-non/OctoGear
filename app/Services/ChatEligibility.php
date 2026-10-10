<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ChatEligibility
{
    // Call with repository-loaded relations; these rules perform no explicit queries.
    public function canSend(Conversation $conversation): bool
    {
        Log::alert([$conversation->customer, $conversation->provider, $conversation->offer?->store, $conversation->provider_id]);

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
        // Store visibility must not prevent discussing an offer already made.
        return $store !== null && $store->user_id === $providerId;
    }

    private function participantsCanSend(?User $customer, ?User $provider): bool
    {
        return $customer !== null && $provider !== null
            && ! $customer->isBlocked() && ! $provider->isBlocked();
    }
}
