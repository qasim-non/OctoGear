<?php

namespace Database\Factories;

use App\Enums\SectionCondition;
use App\Models\CarSection;
use App\Models\StoreCarSection;
use App\Models\StoresCar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\StoreCarSection>
 */
class StoreCarSectionFactory extends Factory
{
    protected $model = StoreCarSection::class;

    public function definition(): array
    {
        return [
            'store_car_id' => StoresCar::factory(),
            'section_id' => CarSection::factory(),
            'condition' => fake()->randomElement([SectionCondition::Okay, SectionCondition::Damaged]),
        ];
    }
}
