<?php

namespace App\Http\Requests\Shared;

use App\Enums\UserType;
use App\Http\Requests\BaseRequest;
use App\Models\Conversation;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Conversation::class);
    }

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
