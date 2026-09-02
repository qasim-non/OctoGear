<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;
use App\Support\MobileNumber;

class VerifyOtpRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'mobile' => MobileNumber::normalize($this->input('mobile')) ?? $this->input('mobile'),
        ]);
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'regex:/^\+9665\d{8}$/'],
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
