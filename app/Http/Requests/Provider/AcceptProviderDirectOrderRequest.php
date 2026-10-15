<?php

namespace App\Http\Requests\Provider;

use App\Http\Requests\BaseRequest;

class AcceptProviderDirectOrderRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'expected_unit_price' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'expected_quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
