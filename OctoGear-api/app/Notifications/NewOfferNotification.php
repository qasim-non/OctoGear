<?php

namespace App\Notifications;

use App\Models\OrderOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to a customer when a service provider submits an offer on their order.
 */
class NewOfferNotification extends Notification
{
    use Queueable;

    public function __construct(public OrderOffer $offer)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'     => 'new_offer',
            'order_id' => $this->offer->order_id,
            'offer_id' => $this->offer->id,
            'price'    => $this->offer->price,
            'message'  => __('auth.notifications.new_offer'),
        ];
    }
}
