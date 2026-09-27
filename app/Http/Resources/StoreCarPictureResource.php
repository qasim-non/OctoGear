<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreCarPictureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => route('media.store-car-pictures.show', [
                'store' => $this->car->store_id,
                'storeCar' => $this->car_id,
                'storeCarPicture' => $this->id,
            ], false),
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'sort_order' => $this->sort_order,
        ];
    }
}
