<?php

namespace App\Notifications;

use App\Models\OrderOffer;
use Illuminate\Notifications\Notification;

class OfferAcceptedNotification extends Notification
{
    public function __construct(public OrderOffer $offer) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['type' => 'offer_accepted', 'order_id' => $this->offer->order_id,
            'offer_id' => $this->offer->id, 'message' => __('push.offer_accepted')];
    }
}
