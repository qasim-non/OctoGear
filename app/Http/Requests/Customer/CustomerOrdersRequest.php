<?php

namespace App\Http\Requests\Customer;

use App\Enums\OrderStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class CustomerOrdersRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'order_type' => ['nullable', Rule::in(['general', 'specific'])],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
