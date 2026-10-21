<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportConversationResource extends JsonResource
{
    public function __construct($resource, private bool $admin = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'status' => $this->status->value, 'version' => $this->version,
            'unread_count' => (int) ($this->admin ? $this->support_unread_count : $this->user_unread_count),
            'user_read_through_id' => $this->user_read_through_id, 'support_read_through_id' => $this->support_read_through_id,
            'created_at' => $this->created_at->toISOString(), 'updated_at' => $this->updated_at->toISOString(),
            'last_activity_at' => $this->last_activity_at?->toISOString(), 'closed_at' => $this->closed_at?->toISOString(),
            'reopened_at' => $this->reopened_at?->toISOString(),
            'last_message' => $this->latestMessage ? new SupportMessageResource($this->latestMessage) : null,
            ...($this->admin ? ['user' => ['id' => $this->user_id, 'name' => $this->user?->full_name,
                'mobile' => $this->user?->mobile, 'type' => $this->user?->type?->value], 'closed_by' => $this->closed_by] : [])];
    }
}
