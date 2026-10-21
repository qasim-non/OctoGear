<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AdminCarCompanyResource extends ReferenceResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'country' => ReferenceResource::make($this->whenLoaded('country')),
            'models_count' => $this->whenCounted('carNames'),
        ];
    }
}
