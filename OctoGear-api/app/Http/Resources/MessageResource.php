<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'content'    => $this->content,
            'is_read'    => $this->is_read,
            'sender_id'  => $this->sender_id,
            'is_mine'    => $this->sender_id === auth()->id(),
            'created_at' => $this->created_at,
        ];
    }
}
