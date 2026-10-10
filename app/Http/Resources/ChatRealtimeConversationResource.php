<?php

namespace App\Http\Resources;

use App\Data\Chat\ConversationData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Shared only with the two participants' authorized private sessions.
 * @property ConversationData $resource
 */
class ChatRealtimeConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $chat = $this->resource->conversation;
        $latest = $chat->latestMessage->first();

        return [
            'id' => $chat->id,
            'customer_id' => $chat->customer_id,
            'customer_name' => $chat->customer?->full_name,
            'provider_name' => $chat->provider?->full_name,
            'customer_unread' => $chat->customer_unread,
            'provider_unread' => $chat->provider_unread,
            'order_id' => $chat->offer?->order_id,
            'offer_id' => $chat->offer_id,
            'store' => $chat->offer?->store ? (new ChatStoreResource($chat->offer->store))->resolve($request) : null,
            'can_send' => $this->resource->canSend,
            'latest_message' => $latest ? ['id' => $latest->id, 'content' => $latest->content] : null,
            'updated_at' => $chat->updated_at->toISOString(),
        ];
    }
}
