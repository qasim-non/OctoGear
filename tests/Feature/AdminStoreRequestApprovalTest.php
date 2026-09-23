<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Store;
use App\Models\StoreRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStoreRequestApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create();
    }

    public function test_active_admin_can_list_store_requests(): void
    {
        StoreRequest::factory()->pending()->create();
        StoreRequest::factory()->accepted()->create();
        StoreRequest::factory()->rejected()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/store-requests')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_admin_can_filter_store_requests_by_status(): void
    {
        StoreRequest::factory()->pending()->create(['name' => 'Pending Store']);
        StoreRequest::factory()->accepted()->create(['name' => 'Accepted Store']);
        StoreRequest::factory()->rejected()->create(['name' => 'Rejected Store']);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/store-requests?status=pending')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Pending Store');
    }

    public function test_status_filter_must_be_valid(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/store-requests?status=bogus')
            ->assertStatus(422);
    }

    public function test_admin_can_see_store_request_detail(): void
    {
        $storeRequest = StoreRequest::factory()->pending()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/admin/store-requests/{$storeRequest->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $storeRequest->id)
            ->assertJsonPath('data.request_status', 'pending')
            ->assertJsonStructure(['data' => ['user', 'city']]);
    }

    public function test_admin_can_accept_a_pending_store_request_and_store_is_created(): void
    {
        $admin = $this->admin();
        $user = User::factory()->provider()->create();
        $storeRequest = StoreRequest::factory()->pending()->create(['user_id' => $user->id]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/store-requests/{$storeRequest->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.request_status', 'accepted')
            ->assertJsonPath('message', __('auth.admin.store_requests.accepted'));

        $this->assertDatabaseHas('store_requests', [
            'id' => $storeRequest->id,
            'request_status' => 'accepted',
            'processed_by' => $admin->employee_id,
        ]);

        $this->assertDatabaseHas('stores', [
            'user_id' => $user->id,
            'name' => $storeRequest->name,
            'mobile' => $storeRequest->mobile,
            'city_id' => $storeRequest->city_id,
            'status' => 'active',
        ]);
    }

    public function test_admin_cannot_accept_an_already_processed_request(): void
    {
        $accepted = StoreRequest::factory()->accepted()->create();
        $rejected = StoreRequest::factory()->rejected()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/store-requests/{$accepted->id}/accept")
            ->assertStatus(422);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/store-requests/{$rejected->id}/reject", ['reason' => 'Nope'])
            ->assertStatus(422);
    }

    public function test_admin_cannot_accept_request_whose_mobile_is_already_in_use(): void
    {
        $existing = Store::factory()->create();
        $storeRequest = StoreRequest::factory()->pending()->create(['mobile' => $existing->mobile]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/store-requests/{$storeRequest->id}/accept")
            ->assertStatus(422);
    }

    public function test_admin_can_reject_a_pending_store_request_with_reason(): void
    {
        $admin = $this->admin();
        $storeRequest = StoreRequest::factory()->pending()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/store-requests/{$storeRequest->id}/reject", ['reason' => 'Incomplete documents'])
            ->assertOk()
            ->assertJsonPath('data.request_status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Incomplete documents');

        $this->assertDatabaseHas('store_requests', [
            'id' => $storeRequest->id,
            'request_status' => 'rejected',
            'rejection_reason' => 'Incomplete documents',
            'processed_by' => $admin->employee_id,
        ]);

        $this->assertDatabaseMissing('stores', ['user_id' => $storeRequest->user_id]);
    }

    public function test_reject_requires_a_reason(): void
    {
        $storeRequest = StoreRequest::factory()->pending()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/store-requests/{$storeRequest->id}/reject")
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_blocked_admin_is_rejected(): void
    {
        StoreRequest::factory()->pending()->create();

        $this->actingAs(Admin::factory()->blocked()->create(), 'sanctum')
            ->getJson('/api/admin/store-requests')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_customer_user_cannot_access_admin_endpoints(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/admin/store-requests')
            ->assertStatus(403)
            ->assertJsonPath('message', __('auth.middleware.admin_required'));
    }

    public function test_guest_cannot_access_admin_endpoints(): void
    {
        $this->getJson('/api/admin/store-requests')
            ->assertStatus(401);
    }
}
