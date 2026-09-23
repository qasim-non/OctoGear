<?php

namespace Database\Factories;

use App\Models\OrderOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\OrderOffer>
 */
class OrderOfferFactory extends Factory
{
    protected $model = OrderOffer::class;

    public function definition(): array
    {
        return [
            'order_id' => null,
            'store_id' => null,
            'price' => fake()->numberBetween(100, 10000),
            'notes' => fake()->optional(0.5)->sentence(),
        ];
    }
}
