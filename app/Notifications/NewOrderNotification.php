<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to a service provider when a customer places a new order
 * (general orders notify stores in the same city; specific orders notify
 * the store that owns the targeted component).
 */
class NewOrderNotification extends Notification
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
            'type'      => 'new_order',
            'order_id'  => $this->order->id,
            'message'   => __('auth.notifications.new_order', ['id' => $this->order->id]),
            'order_type' => $this->order->order_type->value,
        ];
    }
}
