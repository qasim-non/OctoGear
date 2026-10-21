<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'sender_kind' => $this->sender_kind->value,
            'content' => $this->content, 'client_message_id' => $this->client_message_id,
            'created_at' => $this->created_at->toISOString()];
    }
}
