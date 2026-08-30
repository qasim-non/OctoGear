<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\StoreCarComponentResource;
use App\Http\Resources\StoreCarResource;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Models\StoresCar;

class CustomerStoreController extends Controller
{
    public function index()
    {
        $stores = Store::query()
            ->where('status', StoreStatus::Active)
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
}
