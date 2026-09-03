<?php

namespace Database\Factories;

use App\Models\CarCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\CarCompany>
 */
class CarCompanyFactory extends Factory
{
    protected $model = CarCompany::class;

    public function definition(): array
    {
        return [
            'name_en' => fake()->company(),
            'name_ar' => fake()->company(),
            'country_id' => null,
        ];
    }
}
