<?php

namespace App\Http\Controllers\Api;

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
use App\Services\StorefrontQueryService;

/**
 * Marketplace browsing — shared by customers and providers.
 *
 * Thin wiring only: every read lives in StorefrontQueryService. The
 * `can_manage` flag inside StoreResource tells the frontend whether the current
 * user owns the store, so it can render edit/delete affordances (which call the
 * provider management endpoints) or a plain browse view.
 */
class StoreController extends Controller
{
    public function __construct(private StorefrontQueryService $storefront) {}

    public function index(FilterStoresRequest $request)
    {
        $stores = $this->storefront->activeStores($request->validated());

        return $this->paginated($stores->through(fn ($store) => new StoreResource($store)));
    }

    public function show(Store $store)
    {
        return $this->success(new StoreResource($this->storefront->storeDetail($store)));
    }

    public function cars(Store $store)
    {
        $cars = $this->storefront->carList($store);

        return $this->paginated($cars->through(fn ($car) => new StoreCarResource($car)));
    }

    public function showCar(Store $store, StoresCar $car)
    {
        return $this->success(new StoreCarResource($this->storefront->carDetail($store, $car)));
    }

    public function components(Store $store, StoresCar $car)
    {
        $components = $this->storefront->componentList($store, $car);

        return $this->paginated($components->through(fn ($comp) => new StoreCarComponentResource($comp)));
    }

    public function showComponent(Store $store, StoresCar $car, StoreCarComponent $component)
    {
        return $this->success(new StoreCarComponentResource($this->storefront->componentDetail($store, $car, $component)));
    }

    public function componentCars(ComponentCarSearchRequest $request)
    {
        $results = $this->storefront->componentCarSearch($request->validated());

        return $this->paginated($results->through(fn ($item) => new ComponentCarResource($item)));
    }
}
