<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class OrderIndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['pending', 'rejected', 'negotiating', 'paid', 'completed', 'cancelled'])],
            'type' => ['nullable', Rule::in(['general', 'specific'])],
            'customer' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => __('auth.validation.admin.orders.status_invalid'),
            'type.in' => __('auth.validation.admin.orders.type_invalid'),
            'customer.max' => __('auth.validation.admin.name.max'),
            'mobile.max' => __('auth.validation.admin.mobile.max'),
        ];
    }
}
