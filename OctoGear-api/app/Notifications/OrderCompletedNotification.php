<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the service provider when the customer confirms they received the
 * component, completing the order.
 */
class OrderCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(public Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'     => 'order_completed',
            'order_id' => $this->order->id,
            'message'  => __('auth.notifications.order_completed', ['id' => $this->order->id]),
        ];
    }
}
