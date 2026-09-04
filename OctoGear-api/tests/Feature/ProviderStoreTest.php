<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_can_list_all_their_stores(): void
    {
        $provider = User::factory()->provider()->create();
        Store::factory()->create(['user_id' => $provider->id, 'name' => 'Store A']);
        Store::factory()->create(['user_id' => $provider->id, 'name' => 'Store B']);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/stores')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_provider_can_view_their_store(): void
    {
        $provider = User::factory()->provider()->create();
        $city = City::factory()->create();
        $store = Store::factory()->create(['user_id' => $provider->id, 'name' => 'My Store', 'city_id' => $city->id]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/store/{$store->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'My Store')
            ->assertJsonPath('data.id', $store->id)
            ->assertJsonPath('data.city.id', $city->id);
    }

    public function test_provider_cannot_view_another_providers_store(): void
    {
        $provider = User::factory()->provider()->create();
        $other = Store::factory()->create(['user_id' => User::factory()->provider()]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/store/{$other->id}")
            ->assertStatus(403);
    }

    public function test_provider_can_update_one_of_their_stores(): void
    {
        $provider = User::factory()->provider()->create();
        $first = Store::factory()->create(['user_id' => $provider->id, 'name' => 'First Store']);
        $second = Store::factory()->create(['user_id' => $provider->id, 'name' => 'Second Store']);

        $this->actingAs($provider, 'sanctum')
            ->putJson("/api/provider/store/{$second->id}", ['name' => 'Renamed Second'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Second')
            ->assertJsonPath('data.id', $second->id);

        $this->assertDatabaseHas('stores', ['id' => $first->id, 'name' => 'First Store']);
        $this->assertDatabaseHas('stores', ['id' => $second->id, 'name' => 'Renamed Second']);
    }

    public function test_provider_cannot_update_another_providers_store(): void
    {
        $provider = User::factory()->provider()->create();
        $other = Store::factory()->create(['user_id' => User::factory()->provider()]);

        $this->actingAs($provider, 'sanctum')
            ->putJson("/api/provider/store/{$other->id}", ['name' => 'Hacked'])
            ->assertStatus(403);
    }

    public function test_provider_can_update_mobile_to_another_unique_value(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id, 'mobile' => '+966500000000']);

        $this->actingAs($provider, 'sanctum')
            ->putJson("/api/provider/store/{$store->id}", ['mobile' => '0566666666'])
            ->assertOk()
            ->assertJsonPath('data.mobile', '+966566666666');
    }

    public function test_provider_cannot_use_mobile_of_another_store(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id, 'mobile' => '+966500000000']);
        Store::factory()->create(['mobile' => '+966577777777']);

        $this->actingAs($provider, 'sanctum')
            ->putJson("/api/provider/store/{$store->id}", ['mobile' => '+966577777777'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mobile');
    }

    public function test_provider_can_keep_their_own_mobile(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id, 'mobile' => '+966500000000']);

        $this->actingAs($provider, 'sanctum')
            ->putJson("/api/provider/store/{$store->id}", ['mobile' => '+966500000000'])
            ->assertOk()
            ->assertJsonPath('data.mobile', '+966500000000');
    }

    public function test_non_existent_store_returns_not_found(): void
    {
        $provider = User::factory()->provider()->create();

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/store/999999')
            ->assertStatus(404);
    }

    public function test_customer_cannot_manage_provider_store(): void
    {
        $customer = User::factory()->customer()->create();
        $store = Store::factory()->create();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/provider/store/{$store->id}")
            ->assertStatus(403);

        $this->actingAs($customer, 'sanctum')
            ->putJson("/api/provider/store/{$store->id}", ['name' => 'X'])
            ->assertStatus(403);
    }

    public function test_update_store_validates_invalid_city(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);

        $this->actingAs($provider, 'sanctum')
            ->putJson("/api/provider/store/{$store->id}", ['city_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('city_id');
    }
}
