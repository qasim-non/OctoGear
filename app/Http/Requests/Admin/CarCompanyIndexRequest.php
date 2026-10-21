<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class CarCompanyIndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return array_fill_keys(array_map(fn ($key) => $key.'.*', array_keys($this->rules())), __('admin_catalog.filters_invalid'));
    }
}
