<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerCarRequest;
use App\Http\Resources\CustomerCarResource;
use App\Models\CustomerCar;

class CustomerCarController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', CustomerCar::class);

        $cars = auth()->user()
            ->customerCars()
            ->with(['carName', 'color', 'fuelType', 'pictures'])
            ->latest()
            ->paginate(15);

        return $this->paginated($cars->through(fn ($car) => new CustomerCarResource($car)));
    }

    public function store(StoreCustomerCarRequest $request)
    {
        $this->authorize('create', CustomerCar::class);

        $car = auth()->user()->customerCars()->create($request->validated());

        if ($request->has('pictures')) {
            foreach ($request->validated('pictures') as $picture) {
                $car->pictures()->create(['picture' => $picture]);
            }
        }

        $car->load(['carName', 'color', 'fuelType', 'pictures']);

        return $this->created(new CustomerCarResource($car));
    }

    public function destroy(CustomerCar $customerCar)
    {
        $this->authorize('delete', $customerCar);

        $customerCar->delete();

        return $this->success(__('auth.general.ok'));
    }
}
