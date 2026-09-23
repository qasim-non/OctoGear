<?php

namespace Database\Factories;

use App\Models\FuelType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\FuelType>
 */
class FuelTypeFactory extends Factory
{
    protected $model = FuelType::class;

    public function definition(): array
    {
        return [
            'type_en' => fake()->randomElement(['Gasoline', 'Diesel', 'Electric', 'Hybrid']),
            'type_ar' => fake()->word(),
        ];
    }
}
