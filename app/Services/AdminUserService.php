<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Admin oversight for end users (customers + providers).
 */
class AdminUserService
{
    public function __construct(private AuthSessionService $sessions) {}

    public function index(array $filters): LengthAwarePaginator
    {
        $type = $filters['type'] ?? null;
        $status = $filters['status'] ?? null;
        $name = $filters['name'] ?? null;
        $mobile = $filters['mobile'] ?? null;

        return User::query()
            ->with(['city'])
            ->withCount(['stores', 'orders'])
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($name, fn ($query) => $query->where('full_name', 'like', "%{$name}%"))
            ->when($mobile, fn ($query) => $query->where('mobile', 'like', "%{$mobile}%"))
            ->when($filters['city_id'] ?? null, fn ($query, $cityId) => $query->where('city_id', $cityId))
            ->latest()
            ->paginate(25);
    }

    public function show(User $user): User
    {
        return $user->load(['city'])->loadCount(['stores', 'orders']);
    }

    public function setStatus(User $user, UserStatus $status): User
    {
        return DB::transaction(function () use ($user, $status) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->update(['status' => $status]);
            if ($status === UserStatus::Blocked) {
                $this->sessions->revokeAll($user);
            }

            return $user;
        }, 3);
    }
}
