<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UserStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['unblocked', 'blocked'])],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => __('auth.validation.admin.users.status_invalid'),
        ];
    }
}
