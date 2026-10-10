<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Services\ChatRealtimeService;

class QueueChatRealtime
{
    public function __construct(private ChatRealtimeService $realtime) {}

    public function handle(MessageSent $event): void
    {
        $this->realtime->message($event->message->conversation_id, $event->message->id);
    }
}
