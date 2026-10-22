<?php

namespace App\Http\Resources;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', app()->getLocale());

        return [
            'id' => $this->id,
            'currency' => 'SAR',
            'price_scale' => 100,
            'order_type' => $this->order_type->value,
            'quantity' => $this->when($this->isSpecific(), $this->quantity),
            'images' => OrderImageResource::collection($this->whenLoaded('images')),
            'status' => $this->status->value,
            'accepted_offer_id' => $this->accepted_offer_id,
            'offered_price' => $this->offered_price,
            'requested_unit_price' => $this->when($this->isSpecific(), $this->requested_unit_price),
            'notes' => $this->notes,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer?->id,
                'full_name' => $this->customer?->full_name,
                'mobile' => $this->customer?->mobile,
                'city' => $this->customer?->city ? ['id' => $this->customer->city->id, 'name' => $locale === 'en' ? $this->customer->city->name_en : $this->customer->city->name_ar] : null,
            ]),
            'description' => $this->when($this->isGeneral(), $this->notes),
            'component_id' => $this->when($this->isGeneral(), $this->component_id),
            'component_name' => $this->when($this->isGeneral(), $this->requestedComponentName($locale)),
            'vehicle_details' => new OrderVehicleDetailResource($this->whenLoaded('vehicleDetails'), $this->customer_id),
            'store_car_component' => $this->whenLoaded('storeCarComponent', fn () => $this->storeCarComponent ? [
                'id' => $this->storeCarComponent->id,
                'part_number' => $this->storeCarComponent->part_number,
                'description' => $this->storeCarComponent->description,
                'component_name' => $locale === 'en' ? $this->storeCarComponent->component?->name_en : $this->storeCarComponent->component?->name_ar,
                'car_name' => $locale === 'en' ? $this->storeCarComponent->storeCar?->carName?->name_en : $this->storeCarComponent->storeCar?->carName?->name_ar,
                'manufacturing_year' => $this->storeCarComponent->storeCar?->manufacturing_year,
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
                    'city' => $this->fulfillmentStore()->city ? ['id' => $this->fulfillmentStore()->city->id, 'name' => $locale === 'en' ? $this->fulfillmentStore()->city->name_en : $this->fulfillmentStore()->city->name_ar] : null,
                ] : null,
            ),
            'payment' => $this->when($request->user() instanceof Admin && $request->user()->hasPermission('payments.view') && $this->relationLoaded('payment'), fn () => $this->payment ? [
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
