<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseRequest;

class StoreMessageRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:2000'],
        ];
    }
}
