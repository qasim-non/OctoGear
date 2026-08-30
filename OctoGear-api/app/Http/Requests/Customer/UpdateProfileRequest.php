<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseRequest;

class UpdateProfileRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'string', 'max:100'],
            'city_id'   => ['sometimes', 'integer', 'exists:cities,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.string'   => __('auth.validation.full_name.required'),
            'full_name.max'      => __('auth.validation.full_name.max'),
            'city_id.integer'    => __('auth.validation.city_id.integer'),
            'city_id.exists'     => __('auth.validation.city_id.exists'),
        ];
    }
}
