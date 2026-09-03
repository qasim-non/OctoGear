<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreCarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', app()->getLocale());

        return [
            'id'                  => $this->id,
            'manufacturing_year'  => $this->manufacturing_year,
            'vehicle_plat_number' => $this->vehicle_plat_number,
            'car_name' => $this->whenLoaded('carName', fn () => [
                'id'   => $this->carName->id,
                'name' => $locale === 'en' ? $this->carName->name_en : $this->carName->name_ar,
            ]),
            'color' => $this->whenLoaded('color', fn () => [
                'id'   => $this->color->id,
                'name' => $locale === 'en' ? $this->color->name_en : $this->color->name_ar,
            ]),
            'fuel_type' => $this->whenLoaded('fuelType', fn () => [
                'id'   => $this->fuelType->id,
                'name' => $locale === 'en' ? $this->fuelType->type_en : $this->fuelType->type_ar,
            ]),
            'store' => $this->whenLoaded('store', fn () => [
                'id'   => $this->store->id,
                'name' => $this->store->name,
            ]),
            'components_count' => $this->whenCounted('components'),
            'sections' => $this->whenLoaded('storeCarSections', fn () =>
                $this->storeCarSections->map(fn ($section) => [
                    'section_id' => $section->section_id,
                    'name'       => $locale === 'en' ? $section->section?->name_en : $section->section?->name_ar,
                    'condition'  => $section->condition->value,
                ])
            ),
            'pictures' => $this->whenLoaded('pictures', fn () =>
                $this->pictures->pluck('picture')
            ),
            'created_at' => $this->created_at,
        ];
    }
}
