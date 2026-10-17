<?php

namespace App\Listeners;

use App\Jobs\SendCustomerPush;
use App\Models\User;
use App\Repositories\DeviceTokenRepository;
use App\Services\Push\PushNotificationPayload;
use Illuminate\Notifications\Events\NotificationSent;

/** Shared customer/provider delivery; legacy name keeps existing integrations compatible. */
class QueueCustomerPush
{
    public function __construct(private DeviceTokenRepository $devices, private PushNotificationPayload $payloads) {}

    public function handle(NotificationSent $event): void
    {
        if (! config('push.enabled') || $event->channel !== 'database'
            || ! $event->notifiable instanceof User || $event->notifiable->isBlocked()
            || $this->payloads->data($event->notifiable, $event->notification->id, $event->response->data) === null) {
            return;
        }
        foreach ($this->devices->activeFor($event->notifiable) as $device) {
            SendCustomerPush::dispatch($event->notification->id, $event->notifiable->id, $device->id, $device->personal_access_token_id)
                ->onConnection(config('push.connection'))->onQueue(config('push.queue'))->afterCommit();
        }
    }
}
