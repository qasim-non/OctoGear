<?php

namespace App\Listeners;

use App\Enums\StoreStatus;
use App\Events\OrderCreated;
use App\Models\Store;
use App\Notifications\NewOrderNotification;

/**
 * When a customer creates an order, notify the relevant store(s):
 *   - Specific order: notify the store that owns the targeted component.
 *   - General order: notify active stores in the customer's city.
 */
class NotifyStoresOfNewOrder
{
    public function handle(OrderCreated $event): void
    {
        $order = $event->order;

        if ($order->isGeneral()) {
            $stores = Store::query()
                ->where('status', StoreStatus::Active)
                ->where('city_id', $order->customer?->city_id)
                ->with('owner')
                ->get();

            foreach ($stores as $store) {
                $store->owner?->notify(new NewOrderNotification($order));
            }

            return;
        }

        // Specific order → notify the owning store directly.
        $store = $order->store;
        $store?->owner?->notify(new NewOrderNotification($order));
    }
}
