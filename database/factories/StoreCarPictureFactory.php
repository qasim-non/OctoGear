<?php

namespace Database\Factories;

use App\Models\StoreCarPicture;
use App\Models\StoresCar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreCarPicture>
 */
class StoreCarPictureFactory extends Factory
{
    protected $model = StoreCarPicture::class;

    public function definition(): array
    {
        return [
            'disk' => config('images.disk'),
            'path' => 'store-cars/'.fake()->uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'sort_order' => 0,
            'car_id' => StoresCar::factory(),
        ];
    }
}
