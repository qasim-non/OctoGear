<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** Public inventory data; provider management keeps its existing resource. */
class MarketplaceStoreCarResource extends StoreCarResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        unset($data['can_manage'], $data['created_at']);

        $company = $this->carName?->carCompany;
        $data['company'] = $company ? [
            'id' => $company->id,
            'name' => app()->getLocale() === 'en' ? $company->name_en : $company->name_ar,
        ] : null;

        return $data;
    }
}
