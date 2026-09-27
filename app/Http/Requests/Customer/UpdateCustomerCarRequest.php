<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerCarRequest extends BaseRequest
{
    public function rules(): array
    {
        $year = date('Y');

        return [
            'car_name_id' => ['sometimes', 'integer', Rule::exists('cars_names', 'id')->whereNull('deleted_at')],
            'manufacturing_year' => ['sometimes', 'integer', 'min:1970', "max:$year"],
            'vehicle_plat_number' => ['sometimes', 'string', 'max:50'],
            'color_id' => ['sometimes', 'integer', Rule::exists('colors', 'id')->whereNull('deleted_at')],
            'fuel_type' => ['sometimes', 'integer', Rule::exists('fuel_types', 'id')->whereNull('deleted_at')],
        ];
    }
}
