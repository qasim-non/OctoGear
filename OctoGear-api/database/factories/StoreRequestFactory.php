<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\City;
use App\Models\StoreRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\StoreRequest>
 */
class StoreRequestFactory extends Factory
{
    protected $model = StoreRequest::class;

    public function definition(): array
    {
        return [
            'user_id'                        => User::factory(),
            'name'                           => fake()->company() . ' Auto Parts',
            'mobile'                         => fake()->numerify('+9665########'),
            'nick_name'                      => fake()->lastName(),
            'employee_name'                  => fake()->name(),
            'url_location'                   => fake()->url(),
            'commercial_registration_number' => fake()->numerify('###########'),
            'commercial_registration_picture' => fake()->imageUrl(400, 300, 'business'),
            'city_id'                        => City::factory(),
            'request_status'                 => RequestStatus::Pending,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['request_status' => RequestStatus::Pending]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['request_status' => RequestStatus::Accepted]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['request_status' => RequestStatus::Rejected]);
    }
}
