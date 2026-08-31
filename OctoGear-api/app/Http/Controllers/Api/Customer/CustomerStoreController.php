<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ComponentCarSearchRequest;
use App\Http\Requests\Customer\FilterStoresRequest;
use App\Http\Resources\ComponentCarResource;
use App\Http\Resources\StoreCarComponentResource;
use App\Http\Resources\StoreCarResource;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\StoresCar;

class CustomerStoreController extends Controller
{
    public function index(FilterStoresRequest $request)
    {
        $filters = $request->validated();

        $stores = Store::query()
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

        return $this->paginated($stores->through(fn ($store) => new StoreResource($store)));
    }

    public function show(Store $store)
    {
        $store->load(['city', 'pictures', 'companies']);
        $store->loadAvg('ratings', 'rating');

        return $this->success(new StoreResource($store));
    }

    public function cars(Store $store)
    {
        $cars = $store->cars()
            ->with(['carName', 'color', 'fuelType', 'pictures'])
            ->withCount('components')
            ->latest()
            ->paginate(15);

        return $this->paginated($cars->through(fn ($car) => new StoreCarResource($car)));
    }

    public function components(Store $store, StoresCar $car)
    {
        if ($car->store_id !== $store->id) {
            return $this->notFound(__('auth.general.not_found'));
        }

        $components = $car->components()
            ->with('component')
            ->get();

        return $this->success(StoreCarComponentResource::collection($components));
    }

    public function componentCars(ComponentCarSearchRequest $request)
    {
        $filters = $request->validated();

        $results = StoreCarComponent::query()
            ->where('component_id', $filters['component_id'])
            ->where('stock_quantity', '>=', 1)
            ->when(! empty($filters['city_id']), function ($q) use ($filters) {
                $q->whereHas('storeCar.store', fn ($s) => $s->where('city_id', $filters['city_id']));
            })
            ->when(! empty($filters['car_name_id']), function ($q) use ($filters) {
                $q->whereHas('storeCar', fn ($sc) => $sc->where('car_name_id', $filters['car_name_id']));
            })
            ->when(
                empty($filters['car_name_id']) && ! empty($filters['car_company_id']),
                function ($q) use ($filters) {
                    $q->whereHas('storeCar.carName', fn ($cn) => $cn->where('car_company_id', $filters['car_company_id']));
                }
            )
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

        $results->each(function ($item) {
            if ($item->storeCar && $item->storeCar->relationLoaded('store')) {
                $item->storeCar->store->loadAvg('ratings', 'rating');
            }
        });

        return $this->paginated($results->through(fn ($item) => new ComponentCarResource($item)));
    }
}
