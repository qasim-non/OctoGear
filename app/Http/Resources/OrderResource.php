<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', app()->getLocale());

        return [
            'id' => $this->id,
            'order_type' => $this->order_type->value,
            'quantity' => $this->when($this->isSpecific(), $this->quantity),
            'images' => OrderImageResource::collection($this->whenLoaded('images')),
            'status' => $this->status->value,
            'accepted_offer_id' => $this->accepted_offer_id,
            'offered_price' => $this->offered_price,
            'notes' => $this->notes,
            'description' => $this->when($this->isGeneral(), $this->notes),
            'component_id' => $this->when($this->isGeneral(), $this->component_id),
            'component_name' => $this->when($this->isGeneral(), $this->requestedComponentName($locale)),
            'vehicle_details' => new OrderVehicleDetailResource($this->whenLoaded('vehicleDetails'), $this->customer_id),
            'store_car_component' => $this->whenLoaded('storeCarComponent', fn () => [
                'id' => $this->storeCarComponent->id,
                'part_number' => $this->storeCarComponent->part_number,
                'description' => $this->storeCarComponent->description,
                'price' => $this->storeCarComponent->price,
                'store' => [
                    'id' => $this->storeCarComponent->storeCar?->store?->id,
                    'name' => $this->storeCarComponent->storeCar?->store?->name,
                ],
            ]),
            'offers' => $this->whenLoaded('offers', fn () => OrderOfferResource::collection($this->offers)
            ),
            'accepted_store' => $this->whenLoaded('acceptedStore', fn () => [
                'id' => $this->acceptedStore->id,
                'name' => $this->acceptedStore->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
