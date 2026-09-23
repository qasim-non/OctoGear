<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseRequest;

class FilterStoresRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'query'      => ['nullable', 'string', 'max:100'],
            'city_id'    => ['nullable', 'integer', 'exists:cities,id'],
            'company_id' => ['nullable', 'integer', 'exists:cars_companies,id'],
        ];
    }
}
