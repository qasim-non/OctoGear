<?php

namespace Database\Factories;

use App\Models\StoreCarComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\StoreCarComponent>
 */
class StoreCarComponentFactory extends Factory
{
    protected $model = StoreCarComponent::class;

    public function definition(): array
    {
        return [
            'store_car_id' => null,
            'component_id' => null,
            'part_number' => strtoupper(fake()->bothify('??-####')),
            'description' => fake()->sentence(6),
            'price' => fake()->numberBetween(50, 5000),
            'stock_quantity' => fake()->numberBetween(0, 50),
            'warranty_months' => fake()->optional(0.7)->numberBetween(1, 24),
        ];
    }
}
