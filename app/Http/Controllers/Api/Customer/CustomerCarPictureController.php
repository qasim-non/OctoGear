<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerCarPicturesRequest;
use App\Http\Resources\CustomerCarPictureResource;
use App\Models\CustomerCar;
use App\Models\CustomerCarPicture;
use App\Services\CustomerCarPhotoService;

class CustomerCarPictureController extends Controller
{
    public function __construct(private CustomerCarPhotoService $photos) {}

    public function store(StoreCustomerCarPicturesRequest $request, CustomerCar $customerCar)
    {
        $this->authorize('update', $customerCar);

        $pictures = $this->photos->add(
            $customerCar,
            $request->validated('pictures'),
        );

        return $this->created(CustomerCarPictureResource::collection($pictures));
    }

    public function show(CustomerCar $customerCar, CustomerCarPicture $customerCarPicture)
    {
        $this->authorize('view', $customerCar);

        if (! $this->belongsToCar($customerCar, $customerCarPicture)
            || ! $customerCarPicture->isStoredMedia()) {
            return $this->notFound();
        }

        return $this->photos->stream($customerCarPicture) ?? $this->notFound();
    }

    public function destroy(CustomerCar $customerCar, CustomerCarPicture $customerCarPicture)
    {
        $this->authorize('update', $customerCar);

        if (! $this->belongsToCar($customerCar, $customerCarPicture)
            || ! $customerCarPicture->isStoredMedia()) {
            return $this->notFound();
        }

        $this->photos->delete($customerCarPicture);

        return $this->success(__('auth.general.ok'));
    }

    private function belongsToCar(CustomerCar $customerCar, CustomerCarPicture $picture): bool
    {
        return $picture->car_id === $customerCar->id;
    }
}
