<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'full_name' => $this->full_name,
            'mobile'    => $this->mobile,
            'type'      => $this->type->value,
            'city'      => $this->whenLoaded('city', fn () => [
                'id'   => $this->city->id,
                'name' => app()->getLocale() === 'en'
                    ? $this->city->name_en
                    : $this->city->name_ar,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
