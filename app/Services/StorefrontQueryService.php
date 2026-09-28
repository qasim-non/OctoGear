<?php

namespace App\Services;

use App\Enums\SectionCondition;
use App\Enums\StoreStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Component;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\StoresCar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Owns every read on the marketplace storefront (shared by customers and
 * providers). Read-side only — mirrors SoldQuantityService: builds the
 * filtered, eager-loaded queries, enforces the "car belongs to this store"
 * invariant, and enriches results (ratings/sold quantity).
 */
class StorefrontQueryService
{
    public function __construct(private SoldQuantityService $soldQuantity) {}

    public function activeStores(array $filters): LengthAwarePaginator
    {
        return Store::query()
            ->select('stores.*')
            ->selectSub($this->soldQuantity->subquery(), 'sold_quantity')
            ->where('status', StoreStatus::Active)
            ->when(! empty($filters['query']), function ($q) use ($filters) {
                $q->where('nick_name', 'like', "%{$filters['query']}%");
            })
            ->when(! empty($filters['city_id']), function ($q) use ($filters) {
                $q->where('city_id', $filters['city_id']);
            })
            ->when(! empty($filters['company_id']), function ($q) use ($filters) {
                $q->whereHas('companies', fn ($inner) => $inner->whereKey($filters['company_id']));
            })
            ->with(['city', 'pictures'])
            ->withAvg('ratings', 'rating')
            ->latest()
            ->paginate(15);
    }

    public function storeDetail(Store $store): Store
    {
        $this->ensureMarketplaceVisible($store);
        $store->load(['city', 'pictures', 'companies']);
        $store->loadAvg('ratings', 'rating');
        $store->sold_quantity = $this->soldQuantity->forStore($store->id);

        return $store;
    }

    public function carList(Store $store): LengthAwarePaginator
    {
        $this->ensureMarketplaceVisible($store);

        return $store->cars()
            ->with(['carName.carCompany', 'color', 'fuelType', 'pictures', 'store'])
            ->withCount(['components' => fn ($query) => $query->whereHas('component')])
            ->latest()->orderByDesc('id')
            ->paginate(15);
    }

    public function carDetail(Store $store, StoresCar $car): StoresCar
    {
        $this->ensureMarketplaceVisible($store);
        $this->ensureCarBelongsToStore($store, $car);

        $car->load(['carName.carCompany', 'color', 'fuelType', 'pictures', 'store', 'storeCarSections.section']);
        $car->loadCount(['components' => fn ($query) => $query->whereHas('component')]);

        return $car;
    }

    public function componentList(Store $store, StoresCar $car): LengthAwarePaginator
    {
        $this->ensureMarketplaceVisible($store);
        $this->ensureCarBelongsToStore($store, $car);

        return $car->components()
            ->whereHas('component')
            ->with('component.section')
            ->latest()->orderByDesc('id')
            ->paginate(15);
    }

    public function componentDetail(Store $store, StoresCar $car, StoreCarComponent $component): StoreCarComponent
    {
        $this->ensureMarketplaceVisible($store);
        $this->ensureCarBelongsToStore($store, $car);

        if ($component->store_car_id !== $car->id) {
            $this->notFound();
        }

        $component->load('component.section');
        if (! $component->component) {
            $this->notFound();
        }

        return $component;
    }

    public function componentCarSearch(array $filters): LengthAwarePaginator
    {
        $sectionId = Component::find($filters['component_id'])?->section_id;

        $results = StoreCarComponent::query()
            ->where('component_id', $filters['component_id'])
            ->where('stock_quantity', '>=', 1)
            ->when($sectionId, function ($q) use ($sectionId) {
                $q->whereHas('storeCar.storeCarSections', fn ($section) => $section
                    ->where('section_id', $sectionId)
                    ->where('condition', SectionCondition::Okay));
            })
            ->when(! empty($filters['city_id']), function ($q) use ($filters) {
                $q->whereHas('storeCar.store', fn ($s) => $s->where('city_id', $filters['city_id']));
            })
            ->when(! empty($filters['car_company_id']), function ($q) use ($filters) {
                $q->whereHas('storeCar.carName', fn ($sc) => $sc->where('car_company_id', $filters['car_company_id']));
            })
            ->when(! empty($filters['car_name_id']), function ($q) use ($filters) {
                $q->whereHas('storeCar', fn ($sc) => $sc->where('car_name_id', $filters['car_name_id']));
            })
            ->with([
                'component',
                'storeCar.carName.carCompany',
                'storeCar.color',
                'storeCar.fuelType',
                'storeCar.pictures',
                'storeCar.store.city',
                'storeCar.store.pictures',
            ])
            ->latest('store_car_id')
            ->paginate(15);

        $this->hydrateStoreRatings($results);

        return $results;
    }

    private function hydrateStoreRatings(LengthAwarePaginator $results): void
    {
        $storeIds = $results->pluck('storeCar.store.id')->filter()->unique()->values()->all();

        if ($storeIds === []) {
            return;
        }

        $storeRatings = Store::query()
            ->whereIn('id', $storeIds)
            ->withAvg('ratings', 'rating')
            ->get()
            ->keyBy('id');

        $results->each(function ($item) use ($storeRatings) {
            $storeId = $item->storeCar?->store?->id;
            if ($storeId && $storeRatings->has($storeId)) {
                $item->storeCar->store->ratings_avg_rating = $storeRatings[$storeId]->ratings_avg_rating;
            }
        });
    }

    /**
     * A car is only visible through the store that owns it.
     *
     * @throws BusinessRuleException <404 auth.general.not_found>
     */
    private function ensureCarBelongsToStore(Store $store, StoresCar $car): void
    {
        if ($car->store_id !== $store->id) {
            $this->notFound();
        }
    }

    /**
     * Shared browsing must never reveal an inactive store merely because its
     * numeric identifier is known. Store owners use dedicated provider
     * management routes for inactive inventory.
     *
     * @throws BusinessRuleException <404 auth.general.not_found>
     */
    private function ensureMarketplaceVisible(Store $store): void
    {
        if ($store->status !== StoreStatus::Active) {
            $this->notFound();
        }
    }

    private function notFound(): never
    {
        throw new BusinessRuleException(
            'Not found.',
            'auth.general.not_found',
            [],
            404,
        );
    }
}
