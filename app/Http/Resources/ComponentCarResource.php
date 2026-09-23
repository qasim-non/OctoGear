<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComponentCarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', app()->getLocale());

        $storeCar = $this->storeCar;

        $company = $storeCar && $storeCar->relationLoaded('carName') && $storeCar->carName
            ? [
                'id'   => $storeCar->carName->car_company_id,
                'name' => $locale === 'en'
                    ? $storeCar->carName->carCompany?->name_en ?? null
                    : $storeCar->carName->carCompany?->name_ar ?? null,
            ]
            : null;

        return [
            'store_car_component_id' => $this->id,
            'car' => $storeCar ? new StoreCarResource($storeCar) : null,
            'car_company' => $company,
            'component' => new StoreCarComponentResource($this->resource),
            'store' => $storeCar && $storeCar->relationLoaded('store')
                ? new StoreResource($storeCar->store)
                : null,
        ];
    }
}
