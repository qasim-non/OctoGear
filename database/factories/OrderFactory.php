<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_type' => OrderType::General,
            'quantity' => fake()->numberBetween(1, 10),
            'customer_image' => null,
            'status' => OrderStatus::Pending,
            'offered_price' => null,
            'notes' => fake()->optional(0.5)->sentence(),
            'customer_id' => null,
            'store_car_component_id' => null,
            'model_id' => null,
        ];
    }

    public function specific(): static
    {
        return $this->state(fn () => [
            'order_type' => OrderType::Specific,
        ]);
    }

    public function general(): static
    {
        return $this->state(fn () => [
            'order_type' => OrderType::General,
        ]);
    }
}
