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
            'quantity' => $this->quantity,
            'customer_image' => $this->customer_image,
            'status' => $this->status->value,
            'offered_price' => $this->offered_price,
            'notes' => $this->notes,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer?->id,
                'full_name' => $this->customer?->full_name,
                'mobile' => $this->customer?->mobile,
            ]),
            'car_model' => $this->whenLoaded('carModel', fn () => $this->carModel ? [
                'id' => $this->carModel->id,
                'name' => $locale === 'en'
                    ? $this->carModel->name_en
                    : $this->carModel->name_ar,
            ] : null),
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
            'accepted_store' => $this->whenLoaded('acceptedStore', fn () => $this->acceptedStore ? [
                'id' => $this->acceptedStore->id,
                'name' => $this->acceptedStore->name,
            ] : null),
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
