<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use App\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_can_update_profile(): void
    {
        $provider = User::factory()->provider()->create(['full_name' => 'Old Name']);

        $this->actingAs($provider, 'sanctum')
            ->putJson('/api/provider/profile', ['full_name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.full_name', 'New Name');

        $this->assertDatabaseHas('users', [
            'id'        => $provider->id,
            'full_name' => 'New Name',
        ]);
    }

    public function test_provider_can_update_city(): void
    {
        $city = City::factory()->create();
        $provider = User::factory()->provider()->create();

        $this->actingAs($provider, 'sanctum')
            ->putJson('/api/provider/profile', ['city_id' => $city->id])
            ->assertOk()
            ->assertJsonPath('data.city.id', $city->id);
    }

    public function test_update_profile_validates_invalid_city(): void
    {
        $provider = User::factory()->provider()->create();

        $this->actingAs($provider, 'sanctum')
            ->putJson('/api/provider/profile', ['city_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('city_id');
    }
}
