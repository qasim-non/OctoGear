<?php

namespace Database\Factories;

use App\Models\CarName;
use App\Models\Color;
use App\Models\FuelType;
use App\Models\Store;
use App\Models\StoresCar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoresCar>
 */
class StoresCarFactory extends Factory
{
    protected $model = StoresCar::class;

    public function definition(): array
    {
        return [
            'manufacturing_year' => fake()->numberBetween(2010, 2025),
            'car_name_id' => CarName::factory(),
            'color_id' => Color::factory(),
            'store_id' => Store::factory(),
            'fuel_type' => FuelType::factory(),
        ];
    }
}
