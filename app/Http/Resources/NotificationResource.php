<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'payload'    => is_string($this->data) ? json_decode($this->data, true) : $this->data,
            'read_at'    => $this->read_at,
            'is_read'    => !is_null($this->read_at),   
            'created_at' => $this->created_at,
        ];
    }
}
