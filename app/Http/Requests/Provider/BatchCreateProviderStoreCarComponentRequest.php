<?php

namespace App\Http\Requests\Provider;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class BatchCreateProviderStoreCarComponentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'components'                          => ['required', 'array', 'min:1', 'max:50'],
            'components.*.component_id'            => ['required', 'integer', 'exists:components,id'],
            'components.*.part_number'             => ['required', 'string', 'max:100'],
            'components.*.description'             => ['nullable', 'string', 'max:1000'],
            'components.*.price'                   => ['required', 'numeric', 'min:0'],
            'components.*.stock_quantity'          => ['required', 'integer', 'min:0'],
            'components.*.warranty_months'         => ['nullable', 'integer', 'min:0', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'components.required'                       => __('auth.validation.batch_components.required'),
            'components.array'                          => __('auth.validation.batch_components.array'),
            'components.min'                            => __('auth.validation.batch_components.min'),
            'components.*.component_id.required'        => __('auth.validation.component_id.required'),
            'components.*.component_id.integer'         => __('auth.validation.component_id.integer'),
            'components.*.component_id.exists'          => __('auth.validation.component_id.exists'),
            'components.*.part_number.required'         => __('auth.validation.part_number.required'),
            'components.*.part_number.string'           => __('auth.validation.part_number.string'),
            'components.*.part_number.max'              => __('auth.validation.part_number.max'),
            'components.*.description.string'           => __('auth.validation.description.string'),
            'components.*.description.max'              => __('auth.validation.description.max'),
            'components.*.price.required'               => __('auth.validation.price.required'),
            'components.*.price.numeric'                => __('auth.validation.price.numeric'),
            'components.*.price.min'                    => __('auth.validation.price.min'),
            'components.*.stock_quantity.required'      => __('auth.validation.stock_quantity.required'),
            'components.*.stock_quantity.integer'       => __('auth.validation.stock_quantity.integer'),
            'components.*.stock_quantity.min'           => __('auth.validation.stock_quantity.min'),
            'components.*.warranty_months.integer'      => __('auth.validation.warranty_months.integer'),
            'components.*.warranty_months.min'          => __('auth.validation.warranty_months.min'),
            'components.*.warranty_months.max'          => __('auth.validation.warranty_months.max'),
        ];
    }
}
