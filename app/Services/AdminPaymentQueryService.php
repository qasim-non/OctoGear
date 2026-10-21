<?php

namespace App\Services;

use App\Enums\OrderType;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AdminPaymentQueryService
{
    public function index(array $filters): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;

        // Financial records retain their context even after related soft deletion.
        return Payment::withTrashed()->with([
            'order' => fn ($q) => $q->withTrashed(),
            'order.customer.city', 'order.component',
            'order.acceptedOffer.store' => fn ($q) => $q->withTrashed(),
            'order.storeCarComponent' => fn ($q) => $q->withTrashed(),
            'order.storeCarComponent.component' => fn ($q) => $q->withTrashed(),
            'order.storeCarComponent.storeCar' => fn ($q) => $q->withTrashed(),
            'order.storeCarComponent.storeCar.store' => fn ($q) => $q->withTrashed(),
        ])
            ->when($filters['order_id'] ?? null, fn ($q, $id) => $q->where('order_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('payment_status', $value))
            ->when($filters['method'] ?? null, fn ($q, $value) => $q->where('payment_method', $value))
            ->when($filters['type'] ?? null, fn ($q, $value) => $q->whereHas('order', fn ($order) => $order->withTrashed()->where('order_type', $value)))
            ->when($filters['city_id'] ?? null, fn ($q, $id) => $q->whereHas('order', fn ($order) => $order->withTrashed()
                ->whereHas('customer', fn ($customer) => $customer->where('city_id', $id))))
            ->when($search !== null && $search !== '', fn ($q) => $q->whereHas('order', fn ($order) => $order->withTrashed()->where(fn ($context) => $this->searchContext($context, $search))))
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->where('created_at', '>=', CarbonImmutable::parse($date, 'UTC')->startOfDay()))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->where('created_at', '<', CarbonImmutable::parse($date, 'UTC')->addDay()->startOfDay()))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(25);
    }

    private function searchContext(Builder $query, string $search): void
    {
        $term = '%'.$search.'%';
        $query->whereHas('customer', fn ($q) => $q->where(fn ($q) => $q->where('full_name', 'like', $term)->orWhere('mobile', 'like', $term)))
            ->orWhere(fn ($q) => $q->where('order_type', OrderType::General)
                ->whereHas('acceptedOffer.store', fn ($store) => $store->withTrashed()->where('name', 'like', $term)))
            ->orWhere(fn ($q) => $q->where('order_type', OrderType::Specific)
                ->whereHas('storeCarComponent', fn ($part) => $part->withTrashed()
                    ->whereHas('storeCar', fn ($car) => $car->withTrashed()
                        ->whereHas('store', fn ($store) => $store->withTrashed()->where('name', 'like', $term)))));
    }
}
