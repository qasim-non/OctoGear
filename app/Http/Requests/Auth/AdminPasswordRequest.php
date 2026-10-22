<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;
use App\Support\AdminPassword;

class AdminPasswordRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['password' => [...AdminPassword::rules(), 'confirmed'],
            ...($this->is('api/auth/*') ? ['challenge' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']]
                : ['current_password' => ['required', 'string', 'max:256']])];
    }

    public function messages(): array
    {
        return ['password.min' => __('admin_accounts.password_length'), 'password.max' => __('admin_accounts.password_length'),
            'password.confirmed' => __('admin_accounts.password_confirmation'), 'challenge.*' => __('admin_accounts.challenge_expired')];
    }
}
