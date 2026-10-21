<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class OrderIndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['pending', 'rejected', 'awaiting_payment', 'paid', 'completed', 'cancelled'])],
            'type' => ['nullable', Rule::in(['general', 'specific'])],
            'customer' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'city_id' => ['nullable', 'integer', 'min:1', Rule::exists('cities', 'id')->whereNull('deleted_at')],
            'order_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('date_from'), 'after_or_equal:date_from')],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => __('auth.validation.admin.orders.status_invalid'),
            'type.in' => __('auth.validation.admin.orders.type_invalid'),
            'customer.max' => __('auth.validation.admin.name.max'),
            'mobile.max' => __('auth.validation.admin.mobile.max'),
            'city_id.*' => __('admin_web.filters_invalid'),
            'order_id.*' => __('admin_web.filters_invalid'),
            'date_from.*' => __('admin_web.filters_invalid'),
            'date_to.*' => __('admin_web.filters_invalid'),
            'page.*' => __('admin_web.filters_invalid'),
        ];
    }
}
