<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProviderStoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_filter_is_applied_before_pagination_and_includes_inactive_stores(): void
    {
        $provider = User::factory()->provider()->create();
        Store::factory()->count(15)->create(['user_id' => $provider->id]);
        Store::factory()->inactive()->create(['user_id' => $provider->id, 'created_at' => now()->addMinute()]);
        Store::factory()->count(2)->create();
        $this->actingAs(Admin::factory()->create(), 'sanctum');

        $first = $this->getJson('/api/admin/stores?owner_id='.$provider->id)
            ->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('meta.total', 16)
            ->assertJsonPath('meta.per_page', 15)->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.status', 'inactive');
        foreach ($first->json('data') as $store) {
            $this->assertSame($provider->id, $store['owner']['id']);
        }
        $this->getJson('/api/admin/stores?owner_id='.$provider->id.'&page=2')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.owner.id', $provider->id);
        // Existing unfiltered management keeps its original contract.
        $this->getJson('/api/admin/stores')->assertOk()->assertJsonPath('meta.total', 18);
    }

    public function test_owner_combines_with_name_mobile_and_status_and_does_not_expose_other_owners(): void
    {
        $provider = User::factory()->provider()->create();
        $match = Store::factory()->inactive()->create([
            'user_id' => $provider->id, 'name' => 'Blue Parts', 'mobile' => '+966511111111',
        ]);
        Store::factory()->create(['user_id' => $provider->id, 'name' => 'Blue Active', 'mobile' => '+966511111112']);
        Store::factory()->inactive()->create(['user_id' => $provider->id, 'name' => 'Other Parts', 'mobile' => '+966511111113']);
        Store::factory()->inactive()->create(['user_id' => $provider->id, 'name' => 'Blue Mobile', 'mobile' => '+966522222222']);
        Store::factory()->inactive()->create(['name' => 'Blue Parts', 'mobile' => '+966511111114']);
        $this->actingAs(Admin::factory()->create(), 'sanctum');

        $this->getJson('/api/admin/stores?owner_id='.$provider->id.'&name=Blue&mobile=111111&status=inactive')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
        $emptyProvider = User::factory()->provider()->create();
        $this->getJson('/api/admin/stores?owner_id='.$emptyProvider->id)
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
        $match->delete();
        $this->getJson('/api/admin/stores?owner_id='.$provider->id.'&name=Blue&mobile=111111&status=inactive')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_invalid_unknown_and_deleted_owners_are_rejected_with_localized_errors(): void
    {
        $deleted = User::factory()->provider()->create();
        $deleted->delete();
        $this->actingAs(Admin::factory()->create(), 'sanctum');
        foreach (['abc', '0', '-1', '1.5', '999999', (string) $deleted->id, '1&owner_id[]=2'] as $id) {
            $this->getJson('/api/admin/stores?owner_id='.$id)
                ->assertUnprocessable()->assertJsonValidationErrors('owner_id');
        }
        foreach (['en', 'ar'] as $language) {
            foreach (['abc' => 'owner_invalid', '999999' => 'owner_missing'] as $id => $key) {
                $this->getJson('/api/admin/stores?owner_id='.$id, ['Accept-Language' => $language])
                    ->assertUnprocessable()
                    ->assertJsonPath('errors.owner_id.0', trans('auth.validation.admin.stores.'.$key, [], $language));
            }
        }
    }

    public function test_guests_cannot_read_provider_stores(): void
    {
        $this->getJson('/api/admin/stores?owner_id=1')->assertUnauthorized();
    }

    public function test_customers_and_providers_cannot_read_admin_stores(): void
    {
        foreach ([User::factory()->customer()->create(), User::factory()->provider()->create()] as $user) {
            $this->actingAs($user, 'sanctum')->getJson('/api/admin/stores?owner_id='.$user->id)->assertForbidden();
        }
    }

    public function test_blocked_administrators_cannot_read_provider_stores(): void
    {
        $this->actingAs(Admin::factory()->blocked()->create(), 'sanctum')
            ->getJson('/api/admin/stores?owner_id=1')->assertForbidden();
    }
}
