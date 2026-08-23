<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

class AdminLoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => __('auth.validation.email.required'),
            'email.email'       => __('auth.validation.email.email'),
            'password.required' => __('auth.validation.password.required'),
            'password.min'      => __('auth.validation.password.min'),
        ];
    }
}
