<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'mobile' => fake()->unique()->numerify('+9665########'),
            'type' => UserType::Customer,
            'city_id' => City::factory(),
            'status' => UserStatus::Unblocked,
        ];
    }

    public function customer(): static
    {
        return $this->state(fn () => [
            'type' => UserType::Customer,
        ]);
    }

    public function provider(): static
    {
        return $this->state(fn () => [
            'type' => UserType::ServiceProvider,
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => [
            'status' => UserStatus::Blocked,
        ]);
    }
}
