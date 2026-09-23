<?php

namespace App\Http\Requests\Provider;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class CreateProviderOrderOfferRequest extends BaseRequest
{
    public function rules(): array
    {
        $providerStoreIds = auth()->user()->stores()->pluck('id');

        return [
            'store_id' => [
                'required',
                'integer',
                Rule::in($providerStoreIds),
            ],
            'price'  => ['required', 'integer', 'min:1'],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.required' => __('auth.validation.order.store_required'),
            'store_id.in'       => __('auth.validation.order.store_not_mine'),
            'price.required'    => __('auth.validation.price.required'),
            'price.integer'     => __('auth.validation.price.integer'),
            'price.min'         => __('auth.validation.price.min'),
            'notes.string'      => __('auth.validation.description.string'),
            'notes.max'         => __('auth.validation.description.max'),
        ];
    }
}
