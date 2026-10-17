<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationReadService
{
    public const PAGE_SIZE = 20;

    /** Acknowledge exactly the opened page, never an unbounded date range. */
    public function readPage(User $user, array $ids): array
    {
        return DB::transaction(function () use ($user, $ids): array {
            $owned = $user->notifications()->whereIn('id', $ids)->lockForUpdate()->get();
            abort_unless($owned->count() === count($ids), 404);
            $user->unreadNotifications()->whereIn('id', $owned->modelKeys())->update(['read_at' => now()]);

            return ['ids' => $owned->modelKeys(), 'unread_count' => $user->unreadNotifications()->count()];
        });
    }
}
