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
            'employee_name' => $this->employee_name,
            'url_location' => $this->url_location,
            'commercial_registration_number' => $this->commercial_registration_number,
            'mobile' => $this->mobile,
            'status' => $this->status->value,
            'commercial_registration_picture' => $this->hasRegistrationImage()
                ? route('media.store-registration.show', ['store' => $this->id], false)
                : null,
            'cars_count' => $this->cars_count,
            'average_rating' => $this->ratings_avg_rating === null ? null : (float) $this->ratings_avg_rating,
            'companies' => ReferenceResource::collection($this->whenLoaded('companies')),
            'pictures' => StorePictureResource::collection($this->whenLoaded('pictures')),
            'pending_change_request_id' => $this->whenLoaded('pendingChangeRequest', fn () => $this->pendingChangeRequest?->id),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id' => $this->owner->id,
                'full_name' => $this->owner->full_name,
                'mobile' => $this->owner->mobile,
            ] : null),
            'city' => $this->whenLoaded('city', fn () => $this->city ? [
                'id' => $this->city->id,
                'name' => $locale === 'en' ? $this->city->name_en : $this->city->name_ar,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
