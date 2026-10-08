<?php

namespace App\Http\Resources;

use App\Data\Chat\OfferConversationData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property OfferConversationData $resource */
class OfferConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource;

        return [
            'conversation' => $data->conversation ? new ConversationResource($data->conversation) : null,
            'can_send' => $data->canSend,
            'order_id' => $data->offer->order_id,
            'offer_id' => $data->offer->id,
            'store' => $data->offer->store ? new ChatStoreResource($data->offer->store) : null,
        ];
    }
}
