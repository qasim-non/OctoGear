<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->header('Accept-Language', app()->getLocale());

        return [
            'id'   => $this->id,
            'type' => $this->type,
            'text' => $locale === 'en' ? $this->english_text : $this->arabic_text,
        ];
    }
}
