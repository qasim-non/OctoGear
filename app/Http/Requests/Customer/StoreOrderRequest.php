<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseRequest;
use App\Models\StoreCarComponent;
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
            $rules['store_car_component_id'] = [
                'required',
                'integer',
                'exists:store_car_components,id',
                function ($attribute, $value, $fail) {
                    $component = StoreCarComponent::find($value);

                    if (! $component) {
                        $fail(__('auth.validation.store_car_component.not_found'));

                        return;
                    }

                    if ($component->stock_quantity < 1) {
                        $fail(__('auth.validation.store_car_component.out_of_stock'));

                        return;
                    }

                    $quantity = (int) $this->input('quantity', 1);

                    if ($quantity > $component->stock_quantity) {
                        $fail(__('auth.validation.store_car_component.insufficient_stock', [
                            'stock' => $component->stock_quantity,
                        ]));
                    }
                },
            ];
        } elseif ($orderType === 'general') {
            $rules['model_id'] = ['required', 'integer', 'exists:models,id'];
        }

        return $rules;
    }
}
