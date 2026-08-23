<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

class VerifyOtpRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'regex:/^05\d{8}$/'],
            'otp'    => ['required', 'string', 'digits:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' => __('auth.validation.mobile.required'),
            'mobile.regex'    => __('auth.validation.mobile.regex'),
            'otp.required'    => __('auth.validation.otp.required'),
            'otp.digits'      => __('auth.validation.otp.digits'),
        ];
    }
}
