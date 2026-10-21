<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AdminCarNameResource extends ReferenceResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'company' => AdminCarCompanyResource::make($this->whenLoaded('carCompany')),
        ];
    }
}
