<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', app()->getLocale());

        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'mobile' => $this->mobile,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'stores_count' => $this->stores_count,
            'orders_count' => $this->orders_count,
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city->id,
                'name' => $locale === 'en' ? $this->city->name_en : $this->city->name_ar,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
