<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Admin oversight for end users (customers + providers).
 */
class AdminUserService
{
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
            ->latest()
            ->paginate(15);
    }

    public function show(User $user): User
    {
        return $user->load(['city'])->loadCount(['stores', 'orders']);
    }

    public function setStatus(User $user, UserStatus $status): User
    {
        $user->update(['status' => $status]);

        return $user->refresh();
    }
}
