<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseRequest;

class ComponentCarSearchRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'component_id'   => ['required', 'integer', 'exists:components,id'],
            'city_id'        => ['nullable', 'integer', 'exists:cities,id'],
            'car_company_id' => ['nullable', 'integer', 'exists:cars_companies,id'],
            'car_name_id'    => ['nullable', 'integer', 'exists:cars_names,id'],
        ];
    }
}
