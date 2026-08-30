<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the other party in a conversation when a message is sent.
 */
class NewMessageNotification extends Notification
{
    use Queueable;

    public function __construct(public Message $message)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'            => 'new_message',
            'conversation_id' => $this->message->conversation_id,
            'message_id'      => $this->message->id,
            'sender_id'       => $this->message->sender_id,
            'message'         => __('auth.notifications.new_message'),
        ];
    }
}
