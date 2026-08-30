<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', app()->getLocale());

        return [
            'id'                           => $this->id,
            'name'                         => $this->name,
            'nick_name'                    => $this->nick_name,
            'mobile'                       => $this->mobile,
            'employee_name'                => $this->employee_name,
            'url_location'                 => $this->url_location,
            'commercial_registration_number' => $this->commercial_registration_number,
            'status'                       => $this->status->value,
            'average_rating'               => $this->ratings_avg_rating,
            'city' => $this->whenLoaded('city', fn () => [
                'id'   => $this->city->id,
                'name' => $locale === 'en' ? $this->city->name_en : $this->city->name_ar,
            ]),
            'pictures' => $this->whenLoaded('pictures', fn () =>
                $this->pictures->pluck('picture')
            ),
            'created_at' => $this->created_at,
        ];
    }
}
