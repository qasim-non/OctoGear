<?php

namespace Database\Factories;

use App\Models\CarSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\CarSection>
 */
class CarSectionFactory extends Factory
{
    protected $model = CarSection::class;

    public function definition(): array
    {
        return [
            'name_en' => fake()->word(),
            'name_ar' => fake()->word(),
        ];
    }
}
