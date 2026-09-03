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

    public function test_provider_can_view_their_profile(): void
    {
        $city = City::factory()->create();
        $provider = User::factory()->provider()->create(['full_name' => 'Provider One', 'city_id' => $city->id]);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/profile')
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Provider One')
            ->assertJsonPath('data.type', UserType::ServiceProvider->value)
            ->assertJsonPath('data.city.id', $city->id);
    }

    public function test_customer_cannot_access_provider_profile(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/provider/profile')
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_view_profile(): void
    {
        $this->getJson('/api/provider/profile')
            ->assertStatus(401);
    }

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
