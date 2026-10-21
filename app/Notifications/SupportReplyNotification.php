<?php

namespace App\Notifications;

use App\Models\SupportMessage;
use Illuminate\Notifications\Notification;

class SupportReplyNotification extends Notification
{
    public function __construct(public SupportMessage $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['type' => 'support_reply', 'support_conversation_id' => $this->message->support_conversation_id,
            'support_message_id' => $this->message->id, 'message' => __('support.reply')];
    }
}
