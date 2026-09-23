<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseRequest;

class UpdateCustomerCarRequest extends BaseRequest
{
    public function rules(): array
    {
        $year = date('Y');

        return [
            'car_name_id'         => ['sometimes', 'integer', 'exists:cars_names,id'],
            'manufacturing_year'  => ['sometimes', 'integer', 'min:1970', "max:$year"],
            'vehicle_plat_number' => ['sometimes', 'string', 'max:50'],
            'color_id'            => ['sometimes', 'integer', 'exists:colors,id'],
            'fuel_type'           => ['sometimes', 'integer', 'exists:fuel_types,id'],
            'pictures'            => ['sometimes', 'array'],
            'pictures.*'          => ['string', 'max:255'],
        ];
    }
}
