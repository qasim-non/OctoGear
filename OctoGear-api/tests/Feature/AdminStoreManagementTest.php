<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Models\Admin;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStoreManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_all_stores_including_inactive(): void
    {
        Store::factory()->create(['name' => 'Active Store']);
        Store::factory()->inactive()->create(['name' => 'Inactive Store']);

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->getJson('/api/admin/stores')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_admin_can_filter_stores_by_status(): void
    {
        Store::factory()->create(['name' => 'Active Store']);
        Store::factory()->inactive()->create(['name' => 'Inactive Store']);

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->getJson('/api/admin/stores?status=inactive')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Inactive Store');
    }

    public function test_admin_can_search_stores_by_name(): void
    {
        Store::factory()->create(['name' => 'AlFaris Motors']);
        Store::factory()->create(['name' => 'Blue Workshop']);

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->getJson('/api/admin/stores?name=AlFaris')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'AlFaris Motors');
    }

    public function test_admin_can_search_stores_by_mobile(): void
    {
        Store::factory()->create(['name' => 'AlFaris Motors', 'mobile' => '0551111111']);
        Store::factory()->create(['name' => 'Blue Workshop', 'mobile' => '0552222222']);

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->getJson('/api/admin/stores?mobile=0552222222')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Blue Workshop');
    }

    public function test_admin_can_see_store_detail_with_owner_and_city(): void
    {
        $store = Store::factory()->create(['name' => 'Detail Store']);

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->getJson("/api/admin/stores/{$store->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $store->id)
            ->assertJsonPath('data.name', 'Detail Store')
            ->assertJsonStructure(['data' => ['owner', 'city', 'cars_count']]);
    }

    public function test_admin_can_suspend_a_store(): void
    {
        $store = Store::factory()->create();

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->patchJson("/api/admin/stores/{$store->id}/status", ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('message', __('auth.admin.stores.suspended'));

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'status' => StoreStatus::Inactive->value]);
    }

    public function test_admin_can_activate_a_suspended_store(): void
    {
        $store = Store::factory()->inactive()->create();

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->patchJson("/api/admin/stores/{$store->id}/status", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('message', __('auth.admin.stores.activated'));

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'status' => StoreStatus::Active->value]);
    }

    public function test_store_status_change_requires_valid_status(): void
    {
        $store = Store::factory()->create();

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->patchJson("/api/admin/stores/{$store->id}/status", ['status' => 'suspended'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }
}
