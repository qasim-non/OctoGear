<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', app()->getLocale());

        $name = $locale === 'en'
            ? ($this->name_en ?? $this->type_en)
            : ($this->name_ar ?? $this->type_ar);

        return [
            'id'   => $this->id,
            'name' => $name,
        ];
    }
}
