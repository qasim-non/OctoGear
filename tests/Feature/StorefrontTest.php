<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\StoresCar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function providerUser(): User
    {
        return User::factory()->create(['type' => 'service provider']);
    }

    private function makeStore(User $owner, array $overrides = []): Store
    {
        return Store::factory()->create(array_merge(['user_id' => $owner->id], $overrides));
    }

    public function test_provider_can_browse_the_marketplace_like_a_customer(): void
    {
        $store = $this->makeStore($this->providerUser(), ['name' => 'My Store']);
        $provider = $this->providerUser();

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/stores')
            ->assertOk()
            ->assertJsonPath('data.0.id', $store->id)
            ->assertJsonPath('data.0.name', 'My Store');
    }

    public function test_can_manage_is_true_for_the_owners_own_store(): void
    {
        $owner = $this->providerUser();
        $own = $this->makeStore($owner, ['name' => 'Owned']);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/stores/{$own->id}")
            ->assertOk()
            ->assertJsonPath('data.can_manage', true);
    }

    public function test_can_manage_is_false_for_a_store_owned_by_another_provider(): void
    {
        $other = $this->providerUser();
        $store = $this->makeStore($other, ['name' => 'Someone elses']);
        $viewer = $this->providerUser();

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/stores/{$store->id}")
            ->assertOk()
            ->assertJsonPath('data.can_manage', false);
    }

    public function test_can_manage_is_false_for_a_customer_browsing_any_store(): void
    {
        $store = $this->makeStore($this->providerUser(), ['name' => 'A store']);
        $customer = User::factory()->create(['type' => 'customer']);

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/stores/{$store->id}")
            ->assertOk()
            ->assertJsonPath('data.can_manage', false);
    }

    public function test_provider_can_browse_store_cars_and_components(): void
    {
        $owner = $this->providerUser();
        $store = $this->makeStore($owner);
        StoresCar::factory()->create(['store_id' => $store->id]);
        $viewer = $this->providerUser();

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/stores/{$store->id}/cars")
            ->assertOk()
            ->assertJsonPath('data.0.store.id', $store->id);
    }

    public function test_car_from_another_store_returns_not_found(): void
    {
        $storeA = $this->makeStore($this->providerUser());
        $storeB = $this->makeStore($this->providerUser());
        $foreignCar = StoresCar::factory()->create(['store_id' => $storeB->id]);
        $viewer = $this->providerUser();

        $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/stores/{$storeA->id}/cars/{$foreignCar->id}")
            ->assertStatus(404);
    }

    public function test_inactive_store_is_not_visible_in_the_marketplace(): void
    {
        $store = $this->makeStore($this->providerUser(), ['name' => 'Hidden']);
        $store->update(['status' => StoreStatus::Inactive]);
        $viewer = $this->providerUser();

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/stores')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }
}
