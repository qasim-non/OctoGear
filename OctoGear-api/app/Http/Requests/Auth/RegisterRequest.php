<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

class RegisterRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'temp_token' => ['required', 'string', 'max:64'],
            'full_name'  => ['required', 'string', 'max:100'],
            'city_id'    => ['required', 'integer', 'exists:cities,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'temp_token.required' => __('auth.validation.temp_token.required'),
            'temp_token.max'      => __('auth.validation.temp_token.max'),
            'full_name.required'  => __('auth.validation.full_name.required'),
            'full_name.max'       => __('auth.validation.full_name.max'),
            'city_id.required'    => __('auth.validation.city_id.required'),
            'city_id.integer'     => __('auth.validation.city_id.integer'),
            'city_id.exists'      => __('auth.validation.city_id.exists'),
        ];
    }
}
