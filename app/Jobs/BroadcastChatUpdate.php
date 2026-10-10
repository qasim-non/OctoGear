<?php

namespace App\Jobs;

use App\Data\Chat\ConversationData;
use App\Http\Resources\ChatRealtimeConversationResource;
use App\Models\Conversation;
use App\Repositories\ChatSessionRepository;
use App\Repositories\ConversationRepository;
use App\Services\ChatEligibility;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Broadcast;

class BroadcastChatUpdate implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 30;

    public function __construct(
        public int $conversationId,
        public ?int $messageId = null,
        public ?int $readerId = null,
        public ?int $throughId = null,
    ) {}

    public function backoff(): array
    {
        return [1, 5, 15];
    }

    public function handle(ChatSessionRepository $sessions, ConversationRepository $conversations, ChatEligibility $eligibility): void
    {
        if (! config('chat_realtime.enabled')) {
            return;
        }
        $conversation = Conversation::with('offer')->find($this->conversationId);
        if (! $conversation || ! ($channels = $sessions->channelsFor($conversation))) {
            return;
        }
        $payload = [
            'kind' => $this->messageId === null ? 'read' : 'message',
            'conversation_id' => $conversation->id,
            'offer_id' => $conversation->offer_id,
            'order_id' => $conversation->offer?->order_id,
        ];
        if ($this->messageId !== null) {
            $message = $conversation->messages()->find($this->messageId);
            if (! $message) {
                return;
            }
            $payload['message'] = [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'content' => $message->content,
                'is_read' => (bool) $message->is_read,
                'client_message_id' => $message->client_message_id,
                'created_at' => $message->created_at->toISOString(),
            ];
        } else {
            $payload['reader_id'] = $this->readerId;
            $payload['through_id'] = $this->throughId;
        }
        $payload['snapshot_at'] = now()->toISOString();
        $summary = $conversations->loadRealtimeSummary($conversation);
        $payload['conversation'] = (new ChatRealtimeConversationResource(new ConversationData(
            $summary, $eligibility->canSend($summary),
        )))->resolve();
        foreach (array_chunk($channels, 100) as $batch) {
            Broadcast::connection('reverb')->broadcast($batch, 'chat.updated', $payload);
        }
    }
}
