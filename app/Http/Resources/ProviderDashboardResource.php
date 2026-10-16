<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource;
        foreach ($data['attention'] as &$section) {
            $section['items'] = ProviderDashboardOrderResource::collection($section['items']);
        }
        unset($section);
        $data['recent_orders']['items'] = ProviderDashboardOrderResource::collection($data['recent_orders']['items']);

        return $data;
    }
}
