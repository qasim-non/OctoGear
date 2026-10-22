<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Enums\PreferredLanguage;
use App\Http\Requests\BaseRequest;
use App\Support\AdminPassword;
use App\Support\MobileNumber;
use Illuminate\Validation\Rule;

class AdminAccountSaveRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
        if (is_string($this->input('mobile'))) {
            $this->merge(['mobile' => MobileNumber::normalize($this->input('mobile'))]);
        }
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:255'],
            'mobile' => ['required', 'string', 'regex:/^\+9665\d{8}$/'], 'assigned_role' => ['required', Rule::enum(AdminRole::class)],
            'preferred_language' => ['required', Rule::enum(PreferredLanguage::class)],
            'current_password' => ['required', 'string', 'max:256'],
            ...($this->isMethod('POST') ? ['password' => AdminPassword::rules()] : [
                'password' => ['prohibited'], 'version' => ['required', 'integer', 'min:1'], 'status' => ['required', Rule::enum(AdminStatus::class)],
            ])];
    }

    public function messages(): array
    {
        return ['password.*' => __('admin_accounts.password_length'), 'mobile.*' => __('admin_accounts.mobile_invalid')];
    }
}
