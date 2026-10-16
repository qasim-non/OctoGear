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
use Illuminate\Http\JsonResponse;

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

    public function store(CreateProviderStoreCarComponentRequest $request, Store $store, StoresCar $storeCar)
    {
        if ($error = $this->authorizeInventory($store, $storeCar)) {
            return $error;
        }

        $component = $storeCar->components()->create($request->validated());

        $component->load('component');

        return $this->created(new StoreCarComponentResource($component));
    }

    public function batchStore(BatchCreateProviderStoreCarComponentRequest $request, Store $store, StoresCar $storeCar)
    {
        if ($error = $this->authorizeInventory($store, $storeCar)) {
            return $error;
        }

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

    public function update(UpdateProviderStoreCarComponentRequest $request, Store $store, StoresCar $storeCar, StoreCarComponent $component)
    {
        if ($error = $this->authorizeInventory($store, $storeCar, $component)) {
            return $error;
        }

        $component->update($request->validated());

        $component->load('component');

        return $this->success(new StoreCarComponentResource($component));
    }

    public function destroy(Store $store, StoresCar $storeCar, StoreCarComponent $component)
    {
        if ($error = $this->authorizeInventory($store, $storeCar, $component)) {
            return $error;
        }

        $component->delete();

        return $this->success(__('auth.general.ok'));
    }

    private function authorizeInventory(Store $store, StoresCar $storeCar, ?StoreCarComponent $component = null): ?JsonResponse
    {
        if (! $this->carInStore($store, $storeCar)
            || ($component !== null && ! $this->componentInCar($storeCar, $component))) {
            return $this->notFound(__('auth.general.not_found'));
        }

        $this->authorize('manage', $store);

        return null;
    }
}
