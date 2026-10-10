<?php

namespace App\Data\Chat;

use App\Models\Message;
use Illuminate\Support\Collection;

final readonly class MessageTimeline
{
    /** @param Collection<int, Message> $messages */
    public function __construct(
        public Collection $messages,
        public bool $hasMore,
        public int $readThroughId = 0,
    ) {}
}
