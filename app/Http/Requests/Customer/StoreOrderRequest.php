<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseRequest;
use App\Support\ImageRules;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        // Only the header supplies this server-controlled persistence field.
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        $orderType = $this->input('order_type');

        $rules = [
            'order_type' => ['required', Rule::in(['general', 'specific'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'idempotency_key' => ['nullable', 'uuid'],
            'customer_image' => ['nullable', ...ImageRules::file()],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if ($orderType === 'specific') {
            $rules['store_car_component_id'] = [
                'required',
                'integer',
                'min:1',
                // Availability is checked after an idempotency replay lookup.
            ];
        } elseif ($orderType === 'general') {
            $rules['model_id'] = ['required', 'integer', 'exists:models,id'];
        }

        return $rules;
    }
}
