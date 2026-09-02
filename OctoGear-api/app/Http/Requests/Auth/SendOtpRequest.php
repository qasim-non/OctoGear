<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;
use App\Support\MobileNumber;

class SendOtpRequest extends BaseRequest
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
