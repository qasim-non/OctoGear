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
        $ownerId = $filters['owner_id'] ?? null;

        return Store::query()
            ->with($this->storeRelations())
            ->withCount('cars')
            ->withAvg('ratings', 'rating')
            ->when($ownerId, fn ($query) => $query->where('user_id', $ownerId))
            ->when($filters['city_id'] ?? null, fn ($query, $cityId) => $query->where('city_id', $cityId))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($name !== null && $name !== '', fn ($query) => $query->where('name', 'like', "%{$name}%"))
            ->when($mobile !== null && $mobile !== '', fn ($query) => $query->where('mobile', 'like', "%{$mobile}%"))
            ->latest()
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function show(Store $store): Store
    {
        return $store->load([...$this->storeRelations(), 'companies', 'pictures', 'pendingChangeRequest'])
            ->loadCount('cars')->loadAvg('ratings', 'rating');
    }

    public function setStatus(Store $store, StoreStatus $status): Store
    {
        $store->update(['status' => $status]);

        return $store->refresh();
    }

    private function storeRelations(): array
    {
        return ['city', 'owner'];
    }
}
