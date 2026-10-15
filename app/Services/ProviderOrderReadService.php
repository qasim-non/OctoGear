<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\StoreStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/** Fulfillment queries follow the existing provider/store visibility rules. */
class ProviderOrderReadService
{
    public function owned(int $providerId): Builder
    {
        return Order::query()->where(function ($query) use ($providerId) {
            $query->where(fn ($q) => $q->where('order_type', OrderType::General)
                ->whereHas('acceptedOffer.store', fn ($store) => $store->where('user_id', $providerId)))
                ->orWhere(fn ($q) => $q->where('order_type', OrderType::Specific)
                    ->whereHas('storeCarComponent.storeCar.store', fn ($store) => $store->where('user_id', $providerId)));
        });
    }

    public function details(int $id, int $providerId): Order
    {
        return $this->owned($providerId)->with($this->relations())->findOrFail($id);
    }

    public function relations(): array
    {
        return [
            'customer:id,full_name,city_id', 'customer.city', 'images', 'vehicleDetails', 'component', 'payment',
            'acceptedOffer.store',
            'acceptedOffer.images',
            'storeCarComponent.component',
            'storeCarComponent.storeCar.store',
            'storeCarComponent.storeCar.carName',
            'storeCarComponent.storeCar.carName.carCompany',
            'storeCarComponent.storeCar.color', 'storeCarComponent.storeCar.fuelType',
        ];
    }

    public static function canAccept(Order $order): bool
    {
        $part = $order->storeCarComponent;
        $car = $part?->storeCar;
        $store = $car?->store;

        return $order->isSpecific() && $order->status === OrderStatus::Pending
            && $part && ! $part->trashed() && $car && ! $car->trashed()
            && $store && ! $store->trashed() && $store->status === StoreStatus::Active
            && $order->quantity > 0 && $part->stock_quantity >= $order->quantity
            && $order->requested_unit_price > 0 && $order->requested_unit_price <= 2147483647
            && $order->requested_unit_price * $order->quantity <= 2147483647;
    }
}
