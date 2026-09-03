<?php

namespace Database\Factories;

use App\Models\CarSection;
use App\Models\Component;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Component>
 */
class ComponentFactory extends Factory
{
    protected $model = Component::class;

    public function definition(): array
    {
        return [
            'name_en' => fake()->word(),
            'name_ar' => fake()->word(),
            'section_id' => CarSection::factory(),
        ];
    }
}
