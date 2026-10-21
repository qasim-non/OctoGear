<?php

namespace App\Jobs;

use App\Http\Resources\SupportConversationResource;
use App\Http\Resources\SupportMessageResource;
use App\Models\SupportConversation;
use App\Repositories\SupportConversationRepository;
use App\Repositories\SupportSessionRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Broadcast;

class BroadcastSupportUpdate implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 30;

    public function __construct(public int $conversationId, public string $kind, public ?int $messageId = null) {}

    public function backoff(): array
    {
        return [1, 5, 15];
    }

    public function handle(SupportConversationRepository $conversations, SupportSessionRepository $sessions): void
    {
        if (! config('chat_realtime.enabled') || ! SupportConversation::whereKey($this->conversationId)->exists()) {
            return;
        }
        $conversation = $conversations->summary($this->conversationId);
        $message = $this->messageId ? $conversation->messages()->find($this->messageId) : null;
        foreach ([false, true] as $admin) {
            $channels = $admin ? $sessions->adminChannels() : $sessions->userChannels($conversation->user_id);
            $payload = ['kind' => $this->kind, 'conversation' => (new SupportConversationResource($conversation, $admin))->resolve(),
                'message' => $message ? (new SupportMessageResource($message))->resolve() : null];
            foreach (array_chunk($channels, 100) as $batch) {
                Broadcast::connection('reverb')->broadcast($batch, 'support.updated', $payload);
            }
        }
    }
}
