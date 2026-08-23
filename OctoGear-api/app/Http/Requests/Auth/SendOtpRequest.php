<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

class SendOtpRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'regex:/^05\d{8}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' => __('auth.validation.mobile.required'),
            'mobile.regex'    => __('auth.validation.mobile.regex'),
        ];
    }
}
