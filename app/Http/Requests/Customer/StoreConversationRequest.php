<?php

namespace App\Http\Requests\Customer;

use App\Enums\UserType;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends BaseRequest
{
    public function rules(): array
    {
        $user = $this->user();

        if ($user->isProvider()) {
            return [
                'customer_id' => [
                    'required',
                    'integer',
                    Rule::exists('users', 'id')->where('type', UserType::Customer->value),
                ],
            ];
        }

        return [
            'provider_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('type', UserType::ServiceProvider->value),
            ],
        ];
    }
}
