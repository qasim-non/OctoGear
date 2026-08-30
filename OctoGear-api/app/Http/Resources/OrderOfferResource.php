<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'price'    => $this->price,
            'notes'    => $this->notes,
            'store'    => $this->whenLoaded('store', fn () => [
                'id'   => $this->store->id,
                'name' => $this->store->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
