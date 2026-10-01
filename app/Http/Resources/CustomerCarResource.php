<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerCarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', app()->getLocale());

        return [
            'id' => $this->id,
            'manufacturing_year' => $this->manufacturing_year,
            'transmission_type' => $this->transmission_type?->value,
            'vehicle_plat_number' => $this->vehicle_plat_number,
            'car_name' => [
                'id' => $this->carName->id,
                'name' => $locale === 'en' ? $this->carName->name_en : $this->carName->name_ar,
            ],
            'company' => [
                'id' => $this->carName->carCompany->id,
                'name' => $locale === 'en'
                    ? $this->carName->carCompany->name_en
                    : $this->carName->carCompany->name_ar,
            ],
            'color' => $this->whenLoaded('color', fn () => [
                'id' => $this->color->id,
                'name' => $locale === 'en' ? $this->color->name_en : $this->color->name_ar,
            ]),
            'fuel_type' => $this->whenLoaded('fuelType', fn () => [
                'id' => $this->fuelType->id,
                'name' => $locale === 'en' ? $this->fuelType->type_en : $this->fuelType->type_ar,
            ]),
            'pictures' => $this->whenLoaded('pictures', fn () => CustomerCarPictureResource::collection($this->pictures)
            ),
            'created_at' => $this->created_at,
        ];
    }
}
