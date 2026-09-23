<?php

namespace Database\Factories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'content' => fake()->sentence(),
            'is_read' => fake()->boolean(70),
            'conversation_id' => null,
            'sender_id' => null,
        ];
    }
}
