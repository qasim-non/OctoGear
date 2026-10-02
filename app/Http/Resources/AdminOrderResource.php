<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOrderResource extends JsonResource
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
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer?->id,
                'full_name' => $this->customer?->full_name,
                'mobile' => $this->customer?->mobile,
            ]),
            'description' => $this->when($this->isGeneral(), $this->notes),
            'component_id' => $this->when($this->isGeneral(), $this->component_id),
            'component_name' => $this->when($this->isGeneral(), $this->requestedComponentName($locale)),
            'vehicle_details' => new OrderVehicleDetailResource($this->whenLoaded('vehicleDetails'), $this->customer_id),
            'store_car_component' => $this->whenLoaded('storeCarComponent', fn () => $this->storeCarComponent ? [
                'id' => $this->storeCarComponent->id,
                'part_number' => $this->storeCarComponent->part_number,
                'description' => $this->storeCarComponent->description,
                'price' => $this->storeCarComponent->price,
                'store' => [
                    'id' => $this->storeCarComponent->storeCar?->store?->id,
                    'name' => $this->storeCarComponent->storeCar?->store?->name,
                ],
            ] : null),
            'accepted_store' => $this->when(
                $this->relationLoaded('acceptedOffer') || $this->relationLoaded('storeCarComponent'),
                fn () => $this->fulfillmentStore() ? [
                    'id' => $this->fulfillmentStore()->id,
                    'name' => $this->fulfillmentStore()->name,
                ] : null,
            ),
            'payment' => $this->whenLoaded('payment', fn () => $this->payment ? [
                'id' => $this->payment->id,
                'amount' => $this->payment->amount,
                'payment_method' => $this->payment->payment_method->value,
                'payment_status' => $this->payment->payment_status->value,
            ] : null),
            'offers' => $this->whenLoaded('offers', fn () => OrderOfferResource::collection($this->offers)),
            'created_at' => $this->created_at,
        ];
    }
}
