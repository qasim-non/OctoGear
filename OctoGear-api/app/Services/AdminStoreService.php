<?php

namespace App\Services;

use App\Enums\StoreStatus;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Admin oversight for stores (all stores, including inactive ones).
 */
class AdminStoreService
{
    public function index(array $filters): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;
        $name = $filters['name'] ?? null;
        $mobile = $filters['mobile'] ?? null;

        return Store::query()
            ->with(['city', 'owner'])
            ->withCount('cars')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($name, fn ($query) => $query->where('name', 'like', "%{$name}%"))
            ->when($mobile, fn ($query) => $query->where('mobile', 'like', "%{$mobile}%"))
            ->latest()
            ->paginate(15);
    }

    public function show(Store $store): Store
    {
        return $store->load(['city', 'owner'])->loadCount('cars');
    }

    public function setStatus(Store $store, StoreStatus $status): Store
    {
        $store->update(['status' => $status]);

        return $store->refresh();
    }
}
