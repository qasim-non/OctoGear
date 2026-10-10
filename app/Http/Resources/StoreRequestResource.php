<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', app()->getLocale());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'nick_name' => $this->nick_name,
            'mobile' => $this->mobile,
            'employee_name' => $this->employee_name,
            'url_location' => $this->url_location,
            'commercial_registration_number' => $this->commercial_registration_number,
            'commercial_registration_picture' => $this->hasRegistrationImage()
                ? route('media.store-request-registration.show', ['storeRequest' => $this->id], false)
                : null,
            'request_status' => $this->request_status->value,
            'rejection_reason' => $this->rejection_reason,
            'company_ids' => array_map('intval', $this->company_ids ?? []),
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city->id,
                'name' => $locale === 'en' ? $this->city->name_en : $this->city->name_ar,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
