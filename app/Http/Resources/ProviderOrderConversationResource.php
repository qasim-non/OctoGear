<?php

namespace App\Http\Resources;

use App\Data\Chat\ProviderOrderConversationData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property ProviderOrderConversationData $resource */
class ProviderOrderConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource;
        $order = $data->order;

        return [
            'conversation' => $data->conversation ? new ConversationResource($data->conversation) : null,
            'can_send' => $data->conversation?->canSend ?? true,
            'order_id' => $order->id,
            'offer_id' => $order->isGeneral() ? $order->accepted_offer_id : null,
            'store' => new ChatStoreResource($order->fulfillmentStore()),
            'other_user' => ['id' => $order->customer_id, 'name' => $order->customer->full_name],
        ];
    }
}
