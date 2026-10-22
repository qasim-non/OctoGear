<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use App\Support\AdminPassword;

class AdminAccountActionRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['current_password' => ['required', 'string', 'max:256'], 'version' => ['required', 'integer', 'min:1'],
            ...($this->isMethod('POST') ? ['password' => AdminPassword::rules()] : [])];
    }

    public function messages(): array
    {
        return ['password.*' => __('admin_accounts.password_length')];
    }
}
