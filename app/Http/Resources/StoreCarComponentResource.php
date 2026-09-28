<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreCarComponentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', app()->getLocale());

        return [
            'id' => $this->id,
            'part_number' => $this->part_number,
            'description' => $this->description,
            'price' => $this->price,
            // Existing catalog/payment amounts are integer halalas. Keep the
            // amount unchanged and make the display contract explicit.
            'currency' => 'SAR',
            'price_scale' => 100,
            'stock_quantity' => $this->stock_quantity,
            'warranty_months' => $this->warranty_months,
            'component' => $this->whenLoaded('component', fn () => [
                'id' => $this->component->id,
                'name' => $locale === 'en' ? $this->component->name_en : $this->component->name_ar,
            ]),
            'section' => $this->when(
                $this->relationLoaded('component') && $this->component?->relationLoaded('section'),
                fn () => $this->component->section ? [
                    'id' => $this->component->section->id,
                    'name' => $locale === 'en' ? $this->component->section->name_en : $this->component->section->name_ar,
                ] : null,
            ),
        ];
    }
}
