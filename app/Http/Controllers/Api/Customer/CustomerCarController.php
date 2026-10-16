<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerCarRequest;
use App\Http\Requests\Customer\UpdateCustomerCarRequest;
use App\Http\Resources\CustomerCarResource;
use App\Models\CustomerCar;
use App\Services\CustomerCarService;

class CustomerCarController extends Controller
{
    public function __construct(private CustomerCarService $cars) {}

    public function index()
    {
        $this->authorize('viewAny', CustomerCar::class);

        $cars = auth()->user()
            ->customerCars()
            ->with($this->carRelations())
            ->latest()
            ->get();

        return $this->success(CustomerCarResource::collection($cars));
    }

    public function show(CustomerCar $customerCar)
    {
        $this->authorize('view', $customerCar);

        $customerCar->load($this->carRelations());

        return $this->success(new CustomerCarResource($customerCar));
    }

    public function update(UpdateCustomerCarRequest $request, CustomerCar $customerCar)
    {
        $this->authorize('update', $customerCar);

        $customerCar = $this->cars->update($customerCar, $request->validated());

        $customerCar->load($this->carRelations());

        return $this->success(new CustomerCarResource($customerCar));
    }

    public function store(StoreCustomerCarRequest $request)
    {
        $this->authorize('create', CustomerCar::class);

        $car = $this->cars->create($request->user(), $request->validated());

        $car->load($this->carRelations());

        return $this->created(new CustomerCarResource($car));
    }

    public function destroy(CustomerCar $customerCar)
    {
        $this->authorize('delete', $customerCar);

        $customerCar->delete();

        return $this->success(null, __('auth.general.ok'));
    }

    private function carRelations(): array
    {
        return [
            'carName.carCompany' => fn ($query) => $query->withTrashed(),
            'color',
            'fuelType',
            'pictures',
        ];
    }
}
