<?php

namespace App\Services\Push;

use App\Models\User;

class PushNotificationPayload
{
    public function data(User $user, string $notificationId, array $payload): ?array
    {
        $type = $payload['type'] ?? null;
        $fields = match ($type) {
            'new_message' => ['conversation_id'],
            'support_reply' => ['support_conversation_id', 'support_message_id'],
            'new_offer' => $user->isCustomer() ? ['order_id', 'offer_id'] : null,
            'offer_accepted' => $user->isProvider() ? ['order_id', 'offer_id'] : null,
            'order_paid', 'order_completed' => $user->isProvider() ? ['order_id'] : null,
            'new_order' => $user->isProvider() && ($payload['order_type'] ?? null) === 'specific' ? ['order_id'] : null,
            'store_approved', 'store_rejected' => ['request_id'],
            default => null,
        };
        if ($fields === null || (! $user->isCustomer() && ! $user->isProvider())) {
            return null;
        }
        $data = ['notification_id' => $notificationId, 'recipient_id' => (string) $user->id, 'type' => $type];
        foreach ($fields as $field) {
            if (! isset($payload[$field]) || ! ctype_digit((string) $payload[$field]) || (int) $payload[$field] < 1) {
                return null;
            }
            $data[$field] = (string) $payload[$field];
        }
        if ($type === 'new_order') {
            $data['order_type'] = 'specific';
        }
        if ($type === 'new_message' && isset($payload['message_id'])
            && ctype_digit((string) $payload['message_id']) && (int) $payload['message_id'] > 0) {
            $data['message_id'] = (string) $payload['message_id'];
        }

        return $data;
    }
}
