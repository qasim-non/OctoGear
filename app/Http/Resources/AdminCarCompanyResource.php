<?php

namespace App\Http\Resources;

use App\Support\VehicleCatalogVersion;
use Illuminate\Http\Request;

class AdminCarCompanyResource extends ReferenceResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'country_id' => $this->country_id,
            'version' => VehicleCatalogVersion::for($this->resource),
            'country' => ReferenceResource::make($this->whenLoaded('country')),
            'models_count' => $this->whenCounted('carNames'),
        ];
    }
}
