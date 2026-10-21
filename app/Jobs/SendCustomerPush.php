<?php

namespace App\Jobs;

use App\Exceptions\PushDeliveryException;
use App\Models\SupportConversation;
use App\Models\User;
use App\Repositories\DeviceTokenRepository;
use App\Services\Push\FcmSender;
use App\Services\Push\PushNotificationPayload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Shared customer/provider delivery; legacy name keeps existing integrations compatible. */
class SendCustomerPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 40;

    public function __construct(
        public string $notificationId,
        public int $userId,
        public int $deviceId,
        public int $sessionId,
    ) {}

    public function backoff(): array
    {
        return [60, 120, 300];
    }

    public function handle(DeviceTokenRepository $devices, FcmSender $sender): void
    {
        if (! config('push.enabled')) {
            return;
        }
        $user = User::find($this->userId);
        if (! $user || (! $user->isCustomer() && ! $user->isProvider()) || $user->isBlocked()) {
            return;
        }
        $notification = $user->notifications()->whereKey($this->notificationId)->first();
        if (! $notification || $notification->read_at || $notification->created_at->lt(now()->subDay())) {
            return;
        }
        $device = $devices->findActive($user, $this->deviceId, $this->sessionId);
        if (! $device) {
            return;
        }
        $data = (new PushNotificationPayload)->data($user, $notification->id, $notification->data);
        if ($data === null) {
            return;
        }
        if ($data['type'] === 'support_reply') {
            $conversation = SupportConversation::where('user_id', $user->id)
                ->find($data['support_conversation_id']);
            if (! $conversation || $conversation->user_read_through_id >= (int) $data['support_message_id']) {
                return;
            }
        }
        // Generic lock-screen text; only validated identifiers leave the server.
        $body = trans('push.'.$data['type'], [], $device->locale);
        try {
            $sent = $sender->send($device, $data, $body);
        } catch (PushDeliveryException $error) {
            if (! $error->retryable()) {
                $this->fail($error);

                return;
            }
            if ($this->job && $error->retryAfter !== null && $this->attempts() < $this->tries) {
                $this->release(max($error->retryAfter, $this->backoff()[min($this->attempts() - 1, 2)]));

                return;
            }
            throw $error;
        }
        if (! $sent) {
            $devices->removeInvalid($device);
        }
    }
}
