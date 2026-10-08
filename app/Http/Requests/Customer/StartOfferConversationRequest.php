<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\Shared\StoreMessageRequest;
use Illuminate\Support\Facades\Gate;

class StartOfferConversationRequest extends StoreMessageRequest
{
    public function authorize(): bool
    {
        Gate::forUser($this->user())->authorize('viewConversation', [
            $this->route('offer'), $this->route('order'),
        ]);

        return true;
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'client_message_id' => ['required', 'uuid'],
        ];
    }
}
