<?php

namespace App\Listeners;

use App\Jobs\SendCustomerPush;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Notifications\NewOfferNotification;
use App\Repositories\DeviceTokenRepository;
use Illuminate\Notifications\Events\NotificationSent;

class QueueCustomerPush
{
    public function __construct(private DeviceTokenRepository $devices) {}

    public function handle(NotificationSent $event): void
    {
        if (! config('push.enabled') || $event->channel !== 'database'
            || ! $event->notifiable instanceof User || ! $event->notifiable->isCustomer()
            || $event->notifiable->isBlocked()
            || ! ($event->notification instanceof NewOfferNotification || $event->notification instanceof NewMessageNotification)) {
            return;
        }
        foreach ($this->devices->activeFor($event->notifiable) as $device) {
            SendCustomerPush::dispatch($event->notification->id, $event->notifiable->id, $device->id, $device->personal_access_token_id)
                ->onConnection(config('push.connection'))->onQueue(config('push.queue'))->afterCommit();
        }
    }
}
