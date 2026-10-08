<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\Cursor;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    /** Cursor pagination keeps older rows stable as new notifications arrive. */
    public function inbox(Request $request)
    {
        $input = $request->validate([
            'cursor' => ['nullable', 'string', 'max:2048'],
            'unread' => ['sometimes', 'boolean'],
        ]);
        $cursor = null;
        if (isset($input['cursor'])) {
            $parts = json_decode(base64_decode(strtr($input['cursor'], '-_', '+/'), true) ?: '', true);
            if (! is_array($parts) || ! is_string($parts['created_at'] ?? null)
                || ! is_string($parts['id'] ?? null) || ! is_bool($parts['_pointsToNextItems'] ?? null)
                || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $parts['id'])
                || strlen($parts['created_at']) > 64 || strtotime($parts['created_at']) === false) {
                throw ValidationException::withMessages(['cursor' => [__('auth.general.validation_failed')]]);
            }
            $cursor = new Cursor(['created_at' => $parts['created_at'], 'id' => $parts['id']], $parts['_pointsToNextItems']);
        }
        $notifications = $request->user()->notifications()
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->reorder()->orderByDesc('created_at')->orderByDesc('id')
            ->cursorPaginate(20, ['*'], 'cursor', $cursor);

        return $this->success([
            'items' => NotificationResource::collection($notifications->items()),
            'next_cursor' => $notifications->nextCursor()?->encode(),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function unreadCount(Request $request)
    {
        return $this->success(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

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
