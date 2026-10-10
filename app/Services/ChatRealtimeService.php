<?php

namespace App\Services;

use App\Jobs\BroadcastChatUpdate;
use App\Models\User;

class ChatRealtimeService
{
    public function configuration(User $user): array
    {
        if (! config('chat_realtime.enabled')) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'url' => rtrim(config('chat_realtime.url'), '/').'/app/'.rawurlencode(config('broadcasting.connections.reverb.key')),
            'channel' => 'private-chat.sessions.'.$user->currentAccessToken()->id,
        ];
    }

    public function message(int $conversationId, int $messageId): void
    {
        $this->queue(new BroadcastChatUpdate($conversationId, $messageId));
    }

    public function read(int $conversationId, int $readerId, int $throughId): void
    {
        $this->queue(new BroadcastChatUpdate($conversationId, readerId: $readerId, throughId: $throughId));
    }

    private function queue(BroadcastChatUpdate $job): void
    {
        if (config('chat_realtime.enabled')) {
            dispatch($job->onConnection(config('chat_realtime.queue_connection'))
                ->onQueue(config('chat_realtime.queue'))->afterCommit());
        }
    }
}
