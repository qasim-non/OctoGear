<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\CreateProviderStoreCarRequest;
use App\Http\Requests\Provider\UpdateProviderStoreCarRequest;
use App\Http\Resources\StoreCarResource;
use App\Models\Store;
use App\Models\StoresCar;
use App\Services\StoreCarService;

class ProviderStoreCarController extends Controller
{
    public function __construct(private StoreCarService $cars) {}

    public function index(Store $store)
    {
        $cars = $store->cars()
            ->with(['carName', 'color', 'fuelType', 'pictures'])
            ->withCount('components')
            ->latest()
            ->get();

        return $this->success(StoreCarResource::collection($cars));
    }

    public function store(CreateProviderStoreCarRequest $request, Store $store)
    {
        $this->authorize('manage', $store);

        $car = $this->cars->create($store, $request->validated());

        $car->load(['carName', 'color', 'fuelType', 'pictures', 'storeCarSections.section']);
        $car->loadCount('components');

        return $this->created(new StoreCarResource($car));
    }

    public function show(Store $store, StoresCar $storeCar)
    {
        if ($storeCar->store_id !== $store->id) {
            return $this->notFound(__('auth.general.not_found'));
        }

        $storeCar->load(['carName', 'color', 'fuelType', 'pictures']);
        $storeCar->loadCount('components');

        return $this->success(new StoreCarResource($storeCar));
    }

    public function update(UpdateProviderStoreCarRequest $request, Store $store, StoresCar $storeCar)
    {
        if ($storeCar->store_id !== $store->id) {
            return $this->notFound(__('auth.general.not_found'));
        }

        $this->authorize('manage', $store);

        $storeCar = $this->cars->update($storeCar, $request->validated());

        $storeCar->load(['carName', 'color', 'fuelType', 'pictures', 'storeCarSections.section']);
        $storeCar->loadCount('components');

        return $this->success(new StoreCarResource($storeCar));
    }

    public function destroy(Store $store, StoresCar $storeCar)
    {
        if ($storeCar->store_id !== $store->id) {
            return $this->notFound(__('auth.general.not_found'));
        }

        $this->authorize('manage', $store);

        $storeCar->delete();

        return $this->success(__('auth.general.ok'));
    }
}
