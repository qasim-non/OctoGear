<?php

namespace App\Http\Resources;

use App\Data\ProviderOrderAmounts;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Store;
use App\Services\ProviderOrderReadService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderManagedOrderResource extends JsonResource
{
    public function __construct(Order $resource, private ProviderOrderAmounts $amounts)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', app()->getLocale()) === 'en' ? 'en' : 'ar';
        $name = 'name_'.$locale;
        $order = $this->resource;
        $part = $order->storeCarComponent;
        $car = $part?->storeCar;
        $store = $order->fulfillmentStore();
        $fulfilled = in_array($order->status, [OrderStatus::Paid, OrderStatus::Completed]);

        return [
            'id' => $order->id,
            'order_type' => $order->order_type->value,
            'status' => $order->status->value,
            'part_name' => $order->isGeneral() ? $order->requestedComponentName($locale) : $part?->component?->{$name},
            'component_name' => $order->isGeneral() ? $order->requestedComponentName($locale) : $part?->component?->{$name},
            'part_number' => $part?->part_number,
            'store' => $store ? ['id' => $store->id, 'name' => $store->name] : null,
            'city' => $order->customer?->city ? ['id' => $order->customer->city->id, 'name' => $order->customer->city->{$name}] : null,
            'sold_to' => $fulfilled && $order->customer ? ['id' => $order->customer->id, 'name' => $order->customer->full_name] : null,
            'quantity' => $order->isSpecific() ? $order->quantity : null,
            'requested_unit_price' => $order->requested_unit_price,
            'unit_price' => $this->amounts->unit,
            'total_amount' => $this->amounts->total,
            'gross_amount' => $this->amounts->gross,
            'commission' => $this->amounts->commission,
            'net_amount' => $this->amounts->net,
            'commission_is_estimate' => true,
            'currency' => 'SAR', 'price_scale' => 100,
            'description' => $order->notes,
            // Keep the existing direct-order response fields for older clients.
            'notes' => $order->notes,
            'offered_price' => $order->offered_price,
            'component_id' => $this->when($order->isGeneral(), $order->component_id),
            'store_car_component' => $this->when($order->isSpecific(), $part ? [
                'id' => $part->id, 'part_number' => $part->part_number,
                'description' => $part->description, 'price' => $part->price,
                'store' => $this->storeContact($store),
            ] : null),
            'accepted_store' => $this->storeContact($store),
            'vehicle_details' => $order->isGeneral()
                ? new OrderVehicleDetailResource($order->vehicleDetails, $order->customer_id)
                : ($car ? [
                    'car_name' => $car->carName?->{$name},
                    'company_name' => $car->carName?->carCompany?->{$name},
                    'manufacturing_year' => $car->manufacturing_year,
                    'color_name' => $car->color?->{$name},
                    'fuel_type_name' => $car->fuelType?->{$name},
                ] : null),
            'can_accept' => ProviderOrderReadService::canAccept($order),
            'can_reject' => $order->isSpecific() && $order->status === OrderStatus::Pending,
            'accepted_offer_id' => $order->isGeneral() ? $order->accepted_offer_id : null,
            'images' => OrderImageResource::collection($this->whenLoaded('images')),
            'created_at' => $order->created_at,
        ];
    }

    private function storeContact(?Store $store): ?array
    {
        return $store ? [
            'id' => $store->id,
            'name' => $store->name,
            'employee_name' => $store->employee_name,
            'url_location' => $store->url_location,
        ] : null;
    }
}
