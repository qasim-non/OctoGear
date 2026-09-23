<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseRequest;

class StoreCustomerCarRequest extends BaseRequest
{
    public function rules(): array
    {
        $year = date('Y');

        return [
            'car_name_id'          => ['required', 'integer', 'exists:cars_names,id'],
            'manufacturing_year'   => ['required', 'integer', 'min:1970', "max:$year"],
            'vehicle_plat_number'  => ['required', 'string', 'max:50'],
            'color_id'             => ['required', 'integer', 'exists:colors,id'],
            'fuel_type'            => ['required', 'integer', 'exists:fuel_types,id'],
            'pictures'             => ['nullable', 'array'],
            'pictures.*'           => ['string', 'max:255'],
        ];
    }
}
