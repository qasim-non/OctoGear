<?php

namespace App\Http\Requests\Customer;

use App\Enums\UserType;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'provider_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('type', UserType::ServiceProvider->value),
            ],
        ];
    }
}
