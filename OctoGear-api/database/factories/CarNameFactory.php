<?php

namespace Database\Factories;

use App\Models\CarCompany;
use App\Models\CarName;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\CarName>
 */
class CarNameFactory extends Factory
{
    protected $model = CarName::class;

    public function definition(): array
    {
        return [
            'name_en' => fake()->word(),
            'name_ar' => fake()->word(),
            'car_company_id' => CarCompany::factory(),
        ];
    }
}
