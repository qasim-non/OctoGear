<?php

namespace App\Http\Resources;

use App\Data\Chat\ConversationData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property ConversationData $resource */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $conversation = $this->resource->conversation;
        $otherUser = $conversation->customer_id === $request->user()->id
            ? $conversation->provider : $conversation->customer;
        $latest = $conversation->relationLoaded('latestMessage')
            ? $conversation->latestMessage->first() : null;

        return [
            'id' => $conversation->id,
            'offer_id' => $conversation->offer_id,
            'order_id' => $conversation->offer?->order_id,
            'store' => $conversation->offer?->store ? new ChatStoreResource($conversation->offer->store) : null,
            'can_send' => $this->resource->canSend,
            'other_user' => [
                'id' => $otherUser?->id,
                'name' => $otherUser?->full_name,
            ],
            'latest_message' => $this->when($conversation->relationLoaded('latestMessage'), fn () => $latest ? [
                'id' => $latest->id,
                'content' => $latest->content,
                'sender_id' => $latest->sender_id,
                'created_at' => $latest->created_at,
            ] : null),
            'unread_count' => $conversation->unread_messages_count ?? 0,
            'created_at' => $conversation->created_at,
            'updated_at' => $conversation->updated_at,
        ];
    }
}
