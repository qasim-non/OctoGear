<?php

namespace Database\Factories;

use App\Models\Rating;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Rating>
 */
class RatingFactory extends Factory
{
    protected $model = Rating::class;

    public function definition(): array
    {
        return [
            'customer_id' => null,
            'store_id' => null,
            'order_id' => null,
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->optional(0.7)->sentence(),
        ];
    }
}
