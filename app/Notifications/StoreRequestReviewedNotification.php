<?php

namespace App\Notifications;

use App\Enums\RequestStatus;
use App\Models\StoreRequest;
use Illuminate\Notifications\Notification;

class StoreRequestReviewedNotification extends Notification
{
    public function __construct(public StoreRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $type = $this->request->request_status === RequestStatus::Accepted ? 'store_approved' : 'store_rejected';

        return ['type' => $type, 'request_id' => $this->request->id,
            'message' => __('push.'.$type)];
    }
}
