<?php

namespace App\Http\Requests\Provider;

use App\Http\Requests\BaseRequest;

class ProviderGeneralOrdersRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'city_id' => ['nullable', 'integer', 'min:1', 'exists:cities,id'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
