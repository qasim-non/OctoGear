<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users(): void
    {
        User::factory()->customer()->create(['full_name' => 'Ahmed Customer']);
        User::factory()->provider()->create(['full_name' => 'Fatima Provider']);
        User::factory()->blocked()->customer()->create(['full_name' => 'Blocked User']);

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_admin_can_filter_users_by_type_and_status(): void
    {
        User::factory()->customer()->create(['full_name' => 'Customer One']);
        User::factory()->provider()->create(['full_name' => 'Provider One']);
        User::factory()->blocked()->customer()->create(['full_name' => 'Blocked Customer']);

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->getJson('/api/admin/users?type=customer&status=blocked')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.full_name', 'Blocked Customer');
    }

    public function test_admin_can_search_users_by_name(): void
    {
        User::factory()->customer()->create(['full_name' => 'Sara Ahmed']);
        User::factory()->customer()->create(['full_name' => 'Noura Khalid']);

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->getJson('/api/admin/users?name=Ahmed')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.full_name', 'Sara Ahmed');
    }

    public function test_admin_can_search_users_by_mobile(): void
    {
        User::factory()->customer()->create(['mobile' => '0551111111', 'full_name' => 'Sara']);
        User::factory()->customer()->create(['mobile' => '0552222222', 'full_name' => 'Noura']);

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->getJson('/api/admin/users?mobile=0552222222')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.full_name', 'Noura');
    }

    public function test_admin_can_combine_name_and_mobile_filters(): void
    {
        User::factory()->customer()->create(['mobile' => '0551111111', 'full_name' => 'Sara Ahmed']);
        User::factory()->customer()->create(['mobile' => '0552222222', 'full_name' => 'Sara Khalid']);

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->getJson('/api/admin/users?name=Sara&mobile=0552222222')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.full_name', 'Sara Khalid');
    }

    public function test_admin_can_list_customers_via_dedicated_route(): void
    {
        User::factory()->customer()->create(['full_name' => 'Customer One']);
        User::factory()->customer()->create(['full_name' => 'Customer Two']);
        User::factory()->provider()->create(['full_name' => 'Provider One']);

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->getJson('/api/admin/users/customers')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_admin_can_list_providers_via_dedicated_route(): void
    {
        User::factory()->customer()->create(['full_name' => 'Customer One']);
        User::factory()->provider()->create(['full_name' => 'Provider One']);
        User::factory()->provider()->create(['full_name' => 'Provider Two']);

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->getJson('/api/admin/users/providers')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_dedicated_user_routes_force_their_type(): void
    {
        User::factory()->customer()->create(['full_name' => 'Customer One']);
        User::factory()->provider()->create(['full_name' => 'Provider One']);

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->getJson('/api/admin/users/customers?type=service%20provider')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.full_name', 'Customer One');
    }

    public function test_user_type_filter_must_be_valid(): void
    {
        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->getJson('/api/admin/users?type=bogus')
            ->assertStatus(422);
    }

    public function test_admin_can_see_user_detail(): void
    {
        $user = User::factory()->provider()->create();
        $user->stores()->create([
            'name' => 'Test Store',
            'mobile' => '+966511111111',
            'nick_name' => 'Test',
            'employee_name' => 'Emp',
            'url_location' => 'https://maps.google.com/?q=test',
            'commercial_registration_number' => '1234567890',
            'commercial_registration_picture' => 'storage/test.jpg',
            'city_id' => $user->city_id,
            'status' => 'active',
        ]);

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->getJson("/api/admin/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.stores_count', 1)
            ->assertJsonStructure(['data' => ['city', 'stores_count', 'orders_count']]);
    }

    public function test_admin_can_block_a_user(): void
    {
        $user = User::factory()->customer()->create();

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->patchJson("/api/admin/users/{$user->id}/status", ['status' => 'blocked'])
            ->assertOk()
            ->assertJsonPath('data.status', 'blocked')
            ->assertJsonPath('message', __('auth.admin.users.blocked'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => UserStatus::Blocked->value]);
    }

    public function test_admin_can_unblock_a_user(): void
    {
        $user = User::factory()->blocked()->customer()->create();

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->patchJson("/api/admin/users/{$user->id}/status", ['status' => 'unblocked'])
            ->assertOk()
            ->assertJsonPath('data.status', 'unblocked')
            ->assertJsonPath('message', __('auth.admin.users.unblocked'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => UserStatus::Unblocked->value]);
    }

    public function test_user_status_change_requires_valid_status(): void
    {
        $user = User::factory()->customer()->create();

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->patchJson("/api/admin/users/{$user->id}/status", ['status' => 'active'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }
}
