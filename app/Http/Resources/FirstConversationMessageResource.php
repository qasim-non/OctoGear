<?php

namespace App\Http\Resources;

use App\Data\Chat\ConversationData;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FirstConversationMessageResource extends JsonResource
{
    public function __construct(Message $message, private ConversationData $conversation)
    {
        parent::__construct($message);
    }

    public function toArray(Request $request): array
    {
        return [
            'conversation' => new ConversationResource($this->conversation),
            'message' => new MessageResource($this->resource),
        ];
    }
}
