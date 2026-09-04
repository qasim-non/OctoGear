<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', DatabaseNotification::class);

        $user = auth()->user();
        $notifications = $user->notifications()->latest()->paginate(15);

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
            'unread_count' => $user->unreadNotifications()->count(),
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
