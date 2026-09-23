<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminStoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', app()->getLocale());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'nick_name' => $this->nick_name,
            'mobile' => $this->mobile,
            'status' => $this->status->value,
            'cars_count' => $this->cars_count,
            'average_rating' => $this->average_rating,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'full_name' => $this->owner->full_name,
                'mobile' => $this->owner->mobile,
            ]),
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city->id,
                'name' => $locale === 'en' ? $this->city->name_en : $this->city->name_ar,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
