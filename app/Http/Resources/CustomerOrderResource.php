<?php

namespace App\Http\Resources;

use App\Enums\PaymentStatus;
use App\Services\CustomerOrderManagement;
use Illuminate\Http\Request;

/** Customer history metadata; payment processing and its API stay unchanged. */
class CustomerOrderResource extends OrderResource
{
    public function toArray(Request $request): array
    {
        $name = $request->header('Accept-Language', app()->getLocale()) === 'en' ? 'name_en' : 'name_ar';
        $part = $this->resource->storeCarComponent;
        $car = $part?->storeCar;
        $vehicle = $this->resource->vehicleDetails;
        $payment = $this->resource->payment;

        return [
            ...parent::toArray($request),
            'part_name' => $this->isGeneral() ? $this->requestedComponentName($name === 'name_en' ? 'en' : 'ar') : $part?->component?->{$name},
            'car_name' => $this->isGeneral() ? $vehicle?->{'car_'.$name} : $car?->carName?->{$name},
            'manufacturing_year' => $this->isGeneral() ? $vehicle?->manufacturing_year : $car?->manufacturing_year,
            'currency' => 'SAR',
            'price_scale' => 100,
            'can_edit' => CustomerOrderManagement::canEdit($this->resource),
            'can_delete' => CustomerOrderManagement::canDelete($this->resource),
            'edit_token' => CustomerOrderManagement::token($this->resource),
            'requested_unit_price' => $this->requested_unit_price,
            'offers_count' => (int) $this->offers_count,
            // Historical payment truth comes from the payment record, never
            // from inventory whose listed price may have changed since then.
            'paid_amount' => $payment?->payment_status === PaymentStatus::Paid ? $payment->amount : null,
        ];
    }
}
