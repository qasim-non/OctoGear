<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ProviderGeneralOrderResource extends OrderResource
{
    public function toArray(Request $request): array
    {
        $city = $this->customer?->city;
        $locale = $request->header('Accept-Language', app()->getLocale());

        return [
            ...parent::toArray($request),
            // Marketplace location only; never expose customer contact details.
            'city' => $city ? [
                'id' => $city->id,
                'name' => $locale === 'en' ? $city->name_en : $city->name_ar,
            ] : null,
        ];
    }
}
