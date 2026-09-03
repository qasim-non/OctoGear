<?php

namespace App\Http\Requests\Provider;

use App\Http\Requests\BaseRequest;

class UpdateProviderStoreCarComponentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'component_id'     => ['sometimes', 'integer', 'exists:components,id'],
            'part_number'      => ['sometimes', 'string', 'max:100'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'price'            => ['sometimes', 'numeric', 'min:0'],
            'stock_quantity'   => ['sometimes', 'integer', 'min:0'],
            'warranty_months'  => ['nullable', 'integer', 'min:0', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'component_id.integer'    => __('auth.validation.component_id.integer'),
            'component_id.exists'     => __('auth.validation.component_id.exists'),
            'part_number.string'      => __('auth.validation.part_number.string'),
            'part_number.max'         => __('auth.validation.part_number.max'),
            'description.string'      => __('auth.validation.description.string'),
            'description.max'         => __('auth.validation.description.max'),
            'price.numeric'           => __('auth.validation.price.numeric'),
            'price.min'               => __('auth.validation.price.min'),
            'stock_quantity.integer'  => __('auth.validation.stock_quantity.integer'),
            'stock_quantity.min'      => __('auth.validation.stock_quantity.min'),
            'warranty_months.integer' => __('auth.validation.warranty_months.integer'),
            'warranty_months.min'     => __('auth.validation.warranty_months.min'),
            'warranty_months.max'     => __('auth.validation.warranty_months.max'),
        ];
    }
}
