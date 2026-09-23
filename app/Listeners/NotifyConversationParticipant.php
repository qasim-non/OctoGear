<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Models\User;
use App\Notifications\NewMessageNotification;

/**
 * When a message is sent in a conversation, notify the other participant.
 */
class NotifyConversationParticipant
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;
        $conversation = $message->conversation;

        if (! $conversation) {
            return;
        }

        // Notify the participant who did NOT send this message.
        $recipientId = $message->sender_id === $conversation->customer_id
            ? $conversation->provider_id
            : $conversation->customer_id;

        $recipient = User::find($recipientId);
        $recipient?->notify(new NewMessageNotification($message));
    }
}
