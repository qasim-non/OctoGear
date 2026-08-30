<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $otherUser = $this->customer_id === auth()->id()
            ? $this->provider
            : $this->customer;

        return [
            'id'             => $this->id,
            'other_user' => [
                'id'   => $otherUser?->id,
                'name' => $otherUser?->full_name,
            ],
            'latest_message' => $this->whenLoaded('latestMessage', fn () =>
                $this->latestMessage->first() ? [
                    'id'         => $this->latestMessage->first()->id,
                    'content'    => $this->latestMessage->first()->content,
                    'sender_id'  => $this->latestMessage->first()->sender_id,
                    'created_at' => $this->latestMessage->first()->created_at,
                ] : null
            ),
            'unread_count' => $this->unread_messages_count ?? 0,
            'created_at' => $this->created_at,
        ];
    }
}
