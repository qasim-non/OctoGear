<?php

namespace App\Services;

use App\Models\CustomerCar;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Owns the customer-car persistence workflow (a car plus its pictures,
 * created/updated atomically).
 *
 * Both paths replace the picture set within a single transaction.
 */
class CustomerCarService
{
    public function create(User $customer, array $data): CustomerCar
    {
        return DB::transaction(function () use ($customer, $data) {
            $car = $customer->customerCars()->create(collect($data)->except(['pictures'])->all());

            $this->replacePictures($car, $data['pictures'] ?? []);

            return $car;
        });
    }

    public function update(CustomerCar $car, array $data): CustomerCar
    {
        DB::transaction(function () use ($car, $data) {
            $car->update(collect($data)->except(['pictures'])->all());

            if (array_key_exists('pictures', $data)) {
                $this->replacePictures($car, $data['pictures'] ?? []);
            }
        });

        return $car;
    }

    private function replacePictures(CustomerCar $car, array $pictures): void
    {
        if ($pictures === []) {
            return;
        }

        $car->pictures()->delete();

        $car->pictures()->createMany(
            array_map(fn ($picture) => ['picture' => $picture], $pictures)
        );
    }
}
