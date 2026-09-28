<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', app()->getLocale());
        $canManage = (bool) ($request->user()?->can('manage', $this->resource) ?? false);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'nick_name' => $this->nick_name,
            // These values are required for provider management, but a
            // marketplace browse response must not disclose an employee's
            // contact details, registration details, or management URL.
            'mobile' => $this->when($canManage, $this->mobile),
            'employee_name' => $this->when($canManage, $this->employee_name),
            'url_location' => $this->when($canManage, $this->url_location),
            'commercial_registration_number' => $this->when($canManage, $this->commercial_registration_number),
            'commercial_registration_picture' => $this->when(
                $canManage,
                fn () => $this->hasRegistrationImage()
                    ? route('media.store-registration.show', ['store' => $this->id], false)
                    : null,
            ),
            'status' => $this->status->value,
            // SQL AVG values are returned as decimal strings by MySQL. The
            // JSON API contract is numeric, so mobile clients do not need to
            // guess whether a rating is a number or a formatted display value.
            'average_rating' => $this->ratings_avg_rating === null
                ? null
                : (float) $this->ratings_avg_rating,
            'sold_quantity' => (int) ($this->sold_quantity ?? 0),
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city->id,
                'name' => $locale === 'en' ? $this->city->name_en : $this->city->name_ar,
            ]),
            // A store's supported manufacturers are public marketplace data.
            // They are intentionally localized reference values, not provider
            // management data or a client-inferred result from search filters.
            'companies' => ReferenceResource::collection($this->whenLoaded('companies')),
            'pictures' => StorePictureResource::collection($this->whenLoaded('pictures')),
            'can_manage' => $canManage,
            'created_at' => $this->created_at,
        ];
    }
}
