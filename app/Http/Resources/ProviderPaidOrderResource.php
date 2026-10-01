<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderPaidOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', app()->getLocale());

        $store = $this->sellerStore();

        return [
            'id' => $this->id,
            'order_type' => $this->order_type->value,
            'quantity' => $this->when($this->isSpecific(), $this->quantity),
            'status' => $this->status->value,
            'gross_amount' => $this->resource->gross_amount ?? 0,
            'commission' => $this->resource->commission ?? 0,
            'net_amount' => $this->resource->net_amount ?? 0,
            'sold_to' => [
                'id' => $this->customer?->id,
                'name' => $this->customer?->full_name,
            ],
            'store' => $store ? [
                'id' => $store->id,
                'name' => $store->name,
            ] : null,
            'description' => $this->when($this->isGeneral(), $this->notes),
            'component_id' => $this->when($this->isGeneral(), $this->component_id),
            'component_name' => $this->when($this->isGeneral(), $this->requestedComponentName($locale)),
            'vehicle_details' => new OrderVehicleDetailResource($this->whenLoaded('vehicleDetails'), $this->customer_id),
            'created_at' => $this->created_at,
        ];
    }

    private function sellerStore()
    {
        if ($this->relationLoaded('acceptedStore') && $this->acceptedStore) {
            return $this->acceptedStore;
        }

        return $this->storeCarComponent?->storeCar?->store;
    }
}
