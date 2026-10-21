<?php

namespace App\Http\Requests\Auth;

use App\Enums\PreferredLanguage;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class AdminLoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'preferred_language' => ['sometimes', Rule::enum(PreferredLanguage::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('auth.validation.email.required'),
            'preferred_language.Illuminate\\Validation\\Rules\\Enum' => __('admin_web.language_invalid'),
            'email.email' => __('auth.validation.email.email'),
            'password.required' => __('auth.validation.password.required'),
            'password.min' => __('auth.validation.password.min'),
        ];
    }
}
