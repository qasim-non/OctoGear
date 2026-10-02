<?php

namespace App\Http\Requests\Provider;

use App\Http\Requests\BaseRequest;
use App\Support\ImageRules;

class UpdateProviderOrderOfferRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'price' => ['sometimes', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'images' => ['sometimes', 'array', 'list', 'max:'.config('images.max_files')],
            'images.*' => ImageRules::file(),
        ];
    }

    public function messages(): array
    {
        return [
            'price.integer' => __('auth.validation.price.integer'),
            'price.min' => __('auth.validation.price.min'),
            'notes.string' => __('auth.validation.description.string'),
            'notes.max' => __('auth.validation.description.max'),
        ];
    }
}
