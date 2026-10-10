<?php

namespace App\Http\Resources;

use App\Data\Chat\MessageTimeline;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property MessageTimeline $resource */
class MessageTimelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'messages' => MessageResource::collection($this->resource->messages),
            'has_more' => $this->resource->hasMore,
            'read_through_id' => $this->resource->readThroughId,
        ];
    }
}
