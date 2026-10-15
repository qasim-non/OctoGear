<?php

namespace App\Http\Resources;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use Illuminate\Http\Request;

class ProviderOrderOfferResource extends OrderOfferResource
{
    public function toArray(Request $request): array
    {
        $order = $this->order;
        $pending = $order?->status === OrderStatus::Pending && $this->status === OfferStatus::Pending;

        return [
            ...parent::toArray($request),
            'order_id' => $this->order_id,
            'order_status' => $order?->status?->value ?? 'unavailable',
            'part_name' => $order?->requestedComponentName($request->header('Accept-Language', app()->getLocale())),
            'can_edit' => $pending,
            'can_withdraw' => $pending,
        ];
    }
}
