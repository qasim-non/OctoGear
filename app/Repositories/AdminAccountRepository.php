<?php

namespace App\Repositories;

use App\Models\Admin;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminAccountRepository
{
    public function index(array $filters): LengthAwarePaginator
    {
        $search = $filters['search'] ?? '';

        return Admin::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('mobile', 'like', '%'.$search.'%')->orWhere('employee_id', $search)))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('assigned_role', $role))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('employee_id')->paginate(25);
    }

    public function events(Admin $admin): LengthAwarePaginator
    {
        return DB::table('admin_security_events')->where('admin_id', $admin->getKey())
            ->orderByDesc('id')->paginate(25);
    }
}
