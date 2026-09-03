<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\BatchCreateProviderStoreCarComponentRequest;
use App\Http\Requests\Provider\CreateProviderStoreCarComponentRequest;
use App\Http\Requests\Provider\UpdateProviderStoreCarComponentRequest;
use App\Http\Resources\StoreCarComponentResource;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\StoresCar;

class ProviderStoreCarComponentController extends Controller
{
    private function carInStore(Store $store, StoresCar $storeCar): bool
    {
        return $storeCar->store_id === $store->id;
    }

    private function componentInCar(StoresCar $storeCar, StoreCarComponent $component): bool
    {
        return $component->store_car_id === $storeCar->id;
    }

    public function index(Store $store, StoresCar $storeCar)
    {
        if (! $this->carInStore($store, $storeCar)) {
            return $this->notFound(__('auth.general.not_found'));
        }

        $components = $storeCar->components()
            ->with('component')
            ->latest()
            ->get();

        return $this->success(StoreCarComponentResource::collection($components));
    }

    public function store(CreateProviderStoreCarComponentRequest $request, Store $store, StoresCar $storeCar)
    {
        if (! $this->carInStore($store, $storeCar)) {
            return $this->notFound(__('auth.general.not_found'));
        }

        $this->authorize('manage', $store);

        $component = $storeCar->components()->create($request->validated());

        $component->load('component');

        return $this->created(new StoreCarComponentResource($component));
    }

    public function batchStore(BatchCreateProviderStoreCarComponentRequest $request, Store $store, StoresCar $storeCar)
    {
        if (! $this->carInStore($store, $storeCar)) {
            return $this->notFound(__('auth.general.not_found'));
        }

        $this->authorize('manage', $store);

        $rows = collect($request->validated('components'))
            ->map(fn ($item) => array_merge($item, ['store_car_id' => $storeCar->id]))
            ->all();

        $storeCar->components()->createMany($rows);

        $components = $storeCar->components()
            ->with('component')
            ->latest()
            ->get();

        return $this->success(StoreCarComponentResource::collection($components));
    }

    public function show(Store $store, StoresCar $storeCar, StoreCarComponent $component)
    {
        if (! $this->carInStore($store, $storeCar) || ! $this->componentInCar($storeCar, $component)) {
            return $this->notFound(__('auth.general.not_found'));
        }

        $component->load('component');

        return $this->success(new StoreCarComponentResource($component));
    }

    public function update(UpdateProviderStoreCarComponentRequest $request, Store $store, StoresCar $storeCar, StoreCarComponent $component)
    {
        if (! $this->carInStore($store, $storeCar) || ! $this->componentInCar($storeCar, $component)) {
            return $this->notFound(__('auth.general.not_found'));
        }

        $this->authorize('manage', $store);

        $component->update($request->validated());

        $component->load('component');

        return $this->success(new StoreCarComponentResource($component));
    }

    public function destroy(Store $store, StoresCar $storeCar, StoreCarComponent $component)
    {
        if (! $this->carInStore($store, $storeCar) || ! $this->componentInCar($storeCar, $component)) {
            return $this->notFound(__('auth.general.not_found'));
        }

        $this->authorize('manage', $store);

        $component->delete();

        return $this->success(__('auth.general.ok'));
    }
}
