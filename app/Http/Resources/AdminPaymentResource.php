<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AdminPaymentResource extends PaymentResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [...parent::toArray($request),
            'currency' => 'SAR',
            'price_scale' => 100,
            'updated_at' => $this->updated_at,
            'archived' => $this->trashed(),
            'order' => $this->whenLoaded('order', function () use ($locale) {
                $order = $this->order;
                if (! $order) {
                    return null;
                }
                $store = $order->fulfillmentStore();
                $customer = $order->customer;

                return [
                    'id' => $order->id,
                    'available' => ! $order->trashed(),
                    'type' => $order->order_type->value,
                    'part_name' => $order->isGeneral() ? $order->requestedComponentName($locale) : $order->storeCarComponent?->component?->{$locale === 'en' ? 'name_en' : 'name_ar'},
                    'customer' => $customer ? [
                        'name' => $customer->full_name,
                        'mobile' => $customer->mobile,
                        'city' => $customer->city ? ['id' => $customer->city->id, 'name' => $locale === 'en' ? $customer->city->name_en : $customer->city->name_ar] : null,
                    ] : null,
                    'store' => $store ? ['id' => $store->id, 'name' => $store->name] : null,
                ];
            }),
        ];
    }
}
