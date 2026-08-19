<?php

namespace Database\Factories;

use App\Enums\StoreStatus;
use App\Models\City;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Auto Parts',
            'mobile' => fake->numerify('+9665########'),
            'nick_name' => fake()->lastName(),
            'employee_name' => fake()->name(),
            'url_location' => fake()->url(),
            'status' => StoreStatus::Active,
            'commercial_registration_number' => fake()->numerify('###########'),
            'commercial_registration_picture' => null,
            'city_id' => City::factory(),
            'user_id' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => StoreStatus::Inactive,
        ]);
    }
}
