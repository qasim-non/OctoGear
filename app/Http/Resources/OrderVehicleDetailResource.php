<?php

namespace App\Http\Resources;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderVehicleDetailResource extends JsonResource
{
    public function __construct($resource, private int $customerId)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $language = $request->header('Accept-Language', app()->getLocale()) === 'en' ? 'en' : 'ar';

        return [
            'customer_car_id' => $this->when(
                $request->user() instanceof Admin || $request->user()?->id === $this->customerId,
                $this->customer_car_id,
            ),
            'car_name_id' => $this->car_name_id,
            'car_company_id' => $this->car_company_id,
            'car_name' => $this->{'car_name_'.$language},
            'company_name' => $this->{'company_name_'.$language},
            'manufacturing_year' => $this->manufacturing_year,
            'transmission_type' => $this->transmission_type?->value,
            'color_id' => $this->color_id,
            'color_name' => $this->{'color_name_'.$language},
            'fuel_type' => $this->fuel_type,
            'fuel_type_name' => $this->{'fuel_type_'.$language},
        ];
    }
}
