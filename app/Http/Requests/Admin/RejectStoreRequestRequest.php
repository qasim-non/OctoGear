<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class RejectStoreRequestRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => __('auth.validation.store_requests.reason.required'),
            'reason.max' => __('auth.validation.store_requests.reason.max'),
        ];
    }
}
