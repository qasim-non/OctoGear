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

        return response()->json([
            'success'      => true,
            'message'      => __('auth.general.ok'),
            'data'         => NotificationResource::collection($notifications->items()),
            'meta'         => [
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'per_page'     => $notifications->perPage(),
                'total'        => $notifications->total(),
            ],
            'unread_count' => auth()->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(DatabaseNotification $notification)
    {
        $this->authorize('update', $notification);

        $notification->markAsRead();

        return $this->success(new NotificationResource($notification));
    }

    public function markAllAsRead()
    {
        $this->authorize('viewAny', DatabaseNotification::class);

        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(__('auth.notifications.all_read'));
    }
}
