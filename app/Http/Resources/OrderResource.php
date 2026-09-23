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
            'id'            => $this->id,
            'order_type'    => $this->order_type->value,
            'quantity'      => $this->quantity,
            'customer_image' => $this->customer_image,
            'status'        => $this->status->value,
            'offered_price' => $this->offered_price,
            'notes'         => $this->notes,
            'car_model'     => $this->whenLoaded('carModel', fn () => [
                'id'   => $this->carModel->id,
                'name' => $locale === 'en' ? $this->carModel->name_en : $this->carModel->name_ar,
            ]),
            'store_car_component' => $this->whenLoaded('storeCarComponent', fn () => [
                'id'          => $this->storeCarComponent->id,
                'part_number' => $this->storeCarComponent->part_number,
                'description' => $this->storeCarComponent->description,
                'price'       => $this->storeCarComponent->price,
                'store'       => [
                    'id'   => $this->storeCarComponent->storeCar?->store?->id,
                    'name' => $this->storeCarComponent->storeCar?->store?->name,
                ],
            ]),
            'offers' => $this->whenLoaded('offers', fn () =>
                OrderOfferResource::collection($this->offers)
            ),
            'accepted_store' => $this->whenLoaded('acceptedStore', fn () => [
                'id'   => $this->acceptedStore->id,
                'name' => $this->acceptedStore->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
