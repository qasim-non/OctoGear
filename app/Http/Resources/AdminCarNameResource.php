<?php

namespace App\Http\Resources;

use App\Support\VehicleCatalogVersion;
use Illuminate\Http\Request;

class AdminCarNameResource extends ReferenceResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'car_company_id' => $this->car_company_id,
            'version' => VehicleCatalogVersion::for($this->resource),
            'company' => AdminCarCompanyResource::make($this->whenLoaded('carCompany')),
        ];
    }
}
