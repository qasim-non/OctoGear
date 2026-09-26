<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerCarPictureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Keep this API-relative so Flutter resolves it through the same
            // environment base URL used for every authenticated API request.
            'url' => route('customer.customer-cars.pictures.show', [
                'customerCar' => $this->car_id,
                'customerCarPicture' => $this->id,
            ], false),
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'sort_order' => $this->sort_order,
        ];
    }
}
