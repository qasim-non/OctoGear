<?php

namespace App\Listeners;

use App\Events\OfferCreated;
use App\Notifications\NewOfferNotification;

/**
 * When a provider submits an offer, notify the customer.
 */
class NotifyCustomerOfOffer
{
    public function handle(OfferCreated $event): void
    {
        $offer = $event->offer;
        $offer->order?->customer?->notify(new NewOfferNotification($offer));
    }
}
