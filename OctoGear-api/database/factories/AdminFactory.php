<?php

namespace Database\Factories;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<\App\Models\Admin>
 */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'assigned_role' => AdminRole::Employee,
            'mobile' => fake->numerify('+9665########'),
            'email' => fake->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'status' => AdminStatus::Active,
        ];
    }

    public function blocked(): static
    {
        return $this->state(fn () => [
            'status' => AdminStatus::Blocked,
        ]);
    }
}
