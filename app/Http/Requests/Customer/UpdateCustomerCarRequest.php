<?php

namespace App\Http\Requests\Customer;

use App\Enums\TransmissionType;
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
            'transmission_type' => ['sometimes', 'nullable', 'string', Rule::enum(TransmissionType::class)],
            'color_id' => ['sometimes', 'integer', Rule::exists('colors', 'id')->whereNull('deleted_at')],
            'fuel_type' => ['sometimes', 'integer', Rule::exists('fuel_types', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'transmission_type.string' => __('auth.validation.transmission_type.invalid'),
            'transmission_type.enum' => __('auth.validation.transmission_type.invalid'),
        ];
    }
}
