<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends BaseRequest
{
    public function rules(): array
    {
        $orderType = $this->input('order_type');

        $rules = [
            'order_type'     => ['required', Rule::in(['general', 'specific'])],
            'quantity'       => ['required', 'integer', 'min:1'],
            'customer_image' => ['nullable', 'string', 'max:255'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];

        if ($orderType === 'specific') {
            $rules['store_car_component_id'] = ['required', 'integer', 'exists:store_car_components,id'];
        } elseif ($orderType === 'general') {
            $rules['model_id'] = ['required', 'integer', 'exists:models,id'];
        }

        return $rules;
    }
}
