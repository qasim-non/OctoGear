<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

class SoldQuantityService
{
    /**
     * Returns a query that matches completed orders belonging to a store
     * via either:
     *   1. accepted_store_id (general orders / accepted offers)
     *   2. store_car_component → store_car → store (specific orders)
     */
    private function completedOrdersForStore(Builder $query, int $storeId): void
    {
        $query->where('status', OrderStatus::Completed)
            ->where(function ($q) use ($storeId) {
                $q->where('accepted_store_id', $storeId)
                    ->orWhereIn('store_car_component_id', function ($sub) use ($storeId) {
                        $sub->select('id')
                            ->from('store_car_components')
                            ->whereIn('store_car_id', function ($sub2) use ($storeId) {
                                $sub2->select('id')
                                    ->from('stores_cars')
                                    ->where('store_id', $storeId);
                            });
                    });
            });
    }

    /**
     * Sum of quantity for all completed orders belonging to this store.
     * Used in Store::show (single store).
     */
    public function forStore(int $storeId): int
    {
        $query = Order::query();
        $this->completedOrdersForStore($query, $storeId);

        return (int) $query->sum('quantity');
    }

    /**
     * Correlated subquery that computes sold_quantity for each store row.
     * Used in Store::index (paginated list).
     *
     * In SQL this looks like:
     *   SELECT stores.*, (
     *     SELECT COALESCE(SUM(orders.quantity), 0)
     *     FROM orders
     *     WHERE orders.status = 'completed'
     *       AND (orders.accepted_store_id = stores.id
     *            OR orders.store_car_component_id IN (...))
     *   ) AS sold_quantity
     *   FROM stores ...
     */
    public function subquery(): Builder
    {
        $query = Order::query();
        $query->where('status', OrderStatus::Completed)
            ->where(function ($q) {
                $q->whereColumn('accepted_store_id', 'stores.id')
                    ->orWhereIn('store_car_component_id', function ($sub) {
                        $sub->select('id')
                            ->from('store_car_components')
                            ->whereIn('store_car_id', function ($sub2) {
                                $sub2->select('id')
                                    ->from('stores_cars')
                                    ->whereColumn('store_id', 'stores.id');
                            });
                    });
            })
            ->selectRaw('COALESCE(SUM(quantity), 0)');

        return $query;
    }
}
