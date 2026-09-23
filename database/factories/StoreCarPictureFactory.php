<?php

namespace Database\Factories;

use App\Models\StoreCarPicture;
use App\Models\StoresCar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\StoreCarPicture>
 */
class StoreCarPictureFactory extends Factory
{
    protected $model = StoreCarPicture::class;

    public function definition(): array
    {
        return [
            'picture' => fake()->imageUrl(400, 300, 'car'),
            'car_id' => StoresCar::factory(),
        ];
    }
}
