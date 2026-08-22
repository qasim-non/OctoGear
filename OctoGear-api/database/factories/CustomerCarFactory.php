<?php

namespace Database\Factories;

use App\Models\CustomerCar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\CustomerCar>
 */
class CustomerCarFactory extends Factory
{
    protected $model = CustomerCar::class;

    public function definition(): array
    {
        return [
            'manufacturing_year' => fake()->numberBetween(2005, 2025),
            'vehicle_plat_number' => fake()->numerify('####-###'),
            'car_name_id' => null,
            'color_id' => null,
            'customer_id' => null,
            'fuel_type' => null,
        ];
    }
}
