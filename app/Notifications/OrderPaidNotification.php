<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the service provider when a customer pays for (or confirms) an order's payment.
 */
class OrderPaidNotification extends Notification
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
            'type'     => 'order_paid',
            'order_id' => $this->order->id,
            'amount'   => $this->order->offered_price,
            'message'  => __('auth.notifications.order_paid', ['id' => $this->order->id]),
        ];
    }
}
