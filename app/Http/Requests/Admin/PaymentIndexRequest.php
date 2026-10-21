<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class PaymentIndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'order_id' => ['nullable', 'integer', 'min:1'],
            'city_id' => ['nullable', 'integer', 'min:1', Rule::exists('cities', 'id')->whereNull('deleted_at')],
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'type' => ['nullable', Rule::enum(OrderType::class)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('date_from'), 'after_or_equal:date_from')],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return array_fill_keys(array_map(fn ($key) => $key.'.*', array_keys($this->rules())), __('admin_payments.filters_invalid'));
    }
}
