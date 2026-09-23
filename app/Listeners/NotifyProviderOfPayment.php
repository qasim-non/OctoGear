<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Notifications\OrderPaidNotification;

/**
 * When a customer pays for an order, notify the accepted store's owner.
 */
class NotifyProviderOfPayment
{
    public function handle(OrderPaid $event): void
    {
        $event->order->acceptedStore?->owner?->notify(new OrderPaidNotification($event->order));
    }
}
