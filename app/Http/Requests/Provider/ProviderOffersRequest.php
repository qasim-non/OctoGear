<?php

namespace App\Http\Requests\Provider;

use App\Http\Requests\BaseRequest;

class ProviderOffersRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'order_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
