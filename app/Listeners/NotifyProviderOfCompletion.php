<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Notifications\OrderCompletedNotification;

/**
 * When a customer confirms they received the component, notify the store owner.
 */
class NotifyProviderOfCompletion
{
    public function handle(OrderCompleted $event): void
    {
        $event->order->acceptedStore?->owner?->notify(new OrderCompletedNotification($event->order));
    }
}
