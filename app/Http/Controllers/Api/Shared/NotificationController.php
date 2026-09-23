<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $notifications = $user->notifications()->latest()->paginate(15);

        return $this->paginated(
            $notifications->through(fn ($notification) => new NotificationResource($notification)),
            null,
            ['unread_count' => $user->unreadNotifications()->count()],
        );
    }

    public function markAsRead(DatabaseNotification $notification)
    {
        $this->authorize('update', $notification);

        $notification->markAsRead();

        return $this->success(new NotificationResource($notification));
    }

    public function markAllAsRead()
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(__('auth.notifications.all_read'));
    }
}
