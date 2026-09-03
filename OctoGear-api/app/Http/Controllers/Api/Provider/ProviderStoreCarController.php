<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\CreateProviderStoreCarRequest;
use App\Http\Requests\Provider\UpdateProviderStoreCarRequest;
use App\Http\Resources\StoreCarResource;
use App\Models\Store;
use App\Models\StoresCar;

class ProviderStoreCarController extends Controller
{
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

        $car = $store->cars()->create($request->safe()->except(['pictures', 'sections']));

        $this->syncSections($car, $request->validated('sections'));

        if ($request->has('pictures')) {
            $car->pictures()->createMany(
                collect($request->validated('pictures'))->map(fn ($picture) => ['picture' => $picture])->all()
            );
        }

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

        $storeCar->update($request->safe()->except(['pictures', 'sections']));

        if ($request->has('sections')) {
            $this->syncSections($storeCar, $request->validated('sections'));
        }

        if ($request->has('pictures')) {
            $storeCar->pictures()->delete();

            $storeCar->pictures()->createMany(
                collect($request->validated('pictures'))->map(fn ($picture) => ['picture' => $picture])->all()
            );
        }

        $storeCar->load(['carName', 'color', 'fuelType', 'pictures', 'storeCarSections.section']);
        $storeCar->loadCount('components');

        return $this->success(new StoreCarResource($storeCar));
    }

    /**
     * Replace the car's section-condition report with the given sections.
     */
    private function syncSections(StoresCar $car, array $sections): void
    {
        $car->storeCarSections()->delete();

        $car->storeCarSections()->createMany(
            collect($sections)->map(fn ($section) => [
                'section_id' => $section['section_id'],
                'condition'  => $section['condition'],
            ])->all()
        );
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
