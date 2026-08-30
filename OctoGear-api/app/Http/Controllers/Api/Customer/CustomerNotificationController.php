<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Notifications\DatabaseNotification;

class CustomerNotificationController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', DatabaseNotification::class);

        $notifications = auth()->user()
            ->notifications()
            ->latest()
            ->paginate(15);

        return $this->paginated($notifications->through(fn ($notification) => new NotificationResource($notification)));
    }

    public function markAsRead(DatabaseNotification $notification)
    {
        $this->authorize('update', $notification);

        $notification->markAsRead();

        return $this->success(new NotificationResource($notification));
    }
}
