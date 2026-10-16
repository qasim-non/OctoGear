<?php

namespace App\Http\Requests\Provider;

use App\Http\Requests\Shared\StoreMessageRequest;
use App\Services\ChatEligibility;

class StartProviderOrderConversationRequest extends StoreMessageRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order')->load([
            'customer', 'acceptedOffer.store', 'storeCarComponent.storeCar.store',
        ]);

        $eligibility = app(ChatEligibility::class);

        return $eligibility->canStartProviderOrder($order, $this->user())
            || $eligibility->canReconcileProviderOrder($order, $this->user());
    }

    public function rules(): array
    {
        return [...parent::rules(), 'client_message_id' => ['required', 'uuid']];
    }
}
