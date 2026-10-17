<?php

namespace App\Listeners;

use App\Events\OfferAccepted;
use App\Notifications\OfferAcceptedNotification;

class NotifyProviderOfAcceptedOffer
{
    public function handle(OfferAccepted $event): void
    {
        ($event->offer->store?->owner)?->notify(new OfferAcceptedNotification($event->offer));
    }
}
