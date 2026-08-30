<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseRequest;

class StoreConversationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'provider_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
