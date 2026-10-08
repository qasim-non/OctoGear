<?php

namespace App\Jobs;

use App\Exceptions\PushDeliveryException;
use App\Models\User;
use App\Repositories\DeviceTokenRepository;
use App\Services\Push\FcmSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
        if (! $user || ! $user->isCustomer() || $user->isBlocked()) {
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
        $payload = $notification->data;
        $type = $payload['type'] ?? null;
        if (! in_array($type, ['new_offer', 'new_message'], true)) {
            return;
        }
        $data = [
            'notification_id' => $notification->id,
            'recipient_id' => (string) $user->id,
            'type' => $type,
        ];
        foreach ($type === 'new_offer' ? ['order_id', 'offer_id'] : ['conversation_id'] as $field) {
            if (! isset($payload[$field]) || ! ctype_digit((string) $payload[$field]) || (int) $payload[$field] < 1) {
                return;
            }
            $data[$field] = (string) $payload[$field];
        }
        // No message text, phone numbers, price or store/customer names on lock screens.
        $body = trans('auth.notifications.'.$type, [], $device->locale);
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
