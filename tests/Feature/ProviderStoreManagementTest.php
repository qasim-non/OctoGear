<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CarCompany;
use App\Models\City;
use App\Models\Store;
use App\Models\StoreRequest;
use App\Models\User;
use App\Services\AdminStoreRequestService;
use App\Services\StoreChangeRequestService;
use App\Services\StoreMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProviderStoreManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['images.disk' => 'store_management_tests']);
        Storage::fake('store_management_tests');
    }

    public function test_management_details_are_owner_only_and_include_inactive_stores(): void
    {
        $owner = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $owner->id, 'status' => 'inactive']);
        $this->actingAs($owner, 'sanctum')->getJson("/api/provider/stores/{$store->id}")
            ->assertOk()->assertJsonPath('data.can_manage', true)->assertJsonPath('data.status', 'inactive');
        $this->actingAs(User::factory()->provider()->create(), 'sanctum')
            ->getJson("/api/provider/stores/{$store->id}")->assertForbidden();
        $this->actingAs(User::factory()->customer()->create(), 'sanctum')
            ->getJson("/api/provider/stores/{$store->id}")->assertForbidden();
    }

    public function test_second_store_keeps_its_number_but_cannot_take_first_stores_number(): void
    {
        $owner = User::factory()->provider()->create();
        $first = Store::factory()->create(['user_id' => $owner->id]);
        $second = Store::factory()->create(['user_id' => $owner->id]);
        $this->actingAs($owner, 'sanctum')->putJson("/api/provider/store/{$second->id}", ['mobile' => $second->mobile])->assertUnprocessable()->assertJsonValidationErrors('mobile');
        $this->putJson("/api/provider/store/{$second->id}", ['mobile' => $first->mobile])
            ->assertUnprocessable()->assertJsonValidationErrors('mobile');
    }

    public function test_store_change_keeps_photos_and_brands_live_until_admin_accepts(): void
    {
        $owner = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        app(StoreMediaService::class)->update($store, ['pictures' => [$this->image(), $this->image()]]);
        [$removed, $kept] = $store->pictures()->get()->all();
        $company = CarCompany::factory()->create();
        $id = $this->actingAs($owner, 'sanctum')->post("/api/provider/stores/{$store->id}/change-requests", [
            'change_reason' => 'Update our gallery and supported brands.',
            'base_version' => StoreChangeRequestService::version($store->fresh()),
            'idempotency_key' => (string) Str::uuid(),
            'retained_picture_ids' => [$kept->id], 'pictures' => [$this->image()], 'company_ids' => [$company->id],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonCount(2, 'data.pictures')->json('data.id');
        $this->assertSame([$removed->id, $kept->id], $store->fresh()->pictures->modelKeys());
        app(AdminStoreRequestService::class)->accept(StoreRequest::findOrFail($id), Admin::factory()->create());
        $this->assertSame([$company->id], $store->fresh()->companies->modelKeys());
        $this->assertSame(2, $store->pictures()->count());
        Storage::disk('store_management_tests')->assertMissing([$kept->path, $removed->path]);

        $clearId = $this->post("/api/provider/stores/{$store->id}/change-requests", [
            'change_reason' => 'Remove obsolete photos and brands.',
            'base_version' => StoreChangeRequestService::version($store->fresh()),
            'idempotency_key' => (string) Str::uuid(),
            'retained_picture_ids' => '', 'company_ids' => '',
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonCount(0, 'data.pictures')->json('data.id');
        $this->assertSame(2, $store->pictures()->count());
        app(AdminStoreRequestService::class)->accept(StoreRequest::findOrFail($clearId), Admin::factory()->create());
        $this->assertSame(0, $store->pictures()->count());
        $this->assertSame(0, $store->companies()->count());
    }

    public function test_gallery_ownership_and_combined_limit_are_enforced_without_partial_updates(): void
    {
        $owner = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $other = Store::factory()->create();
        app(StoreMediaService::class)->update($other, ['pictures' => [$this->image()]]);
        $this->actingAs($owner, 'sanctum')->putJson("/api/provider/store/{$store->id}", [
            'retained_picture_ids' => [$other->pictures()->first()->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('retained_picture_ids.0');
        app(StoreMediaService::class)->update($store, ['pictures' => array_map(fn () => $this->image(), range(1, 5))]);
        $originalName = $store->name;
        $this->post("/api/provider/store/{$store->id}", [
            '_method' => 'PUT', 'name' => 'Must roll back',
            'change_reason' => 'Add another gallery photo.',
            'base_version' => StoreChangeRequestService::version($store->fresh()),
            'idempotency_key' => (string) Str::uuid(),
            'retained_picture_ids' => $store->pictures()->pluck('id')->all(), 'pictures' => [$this->image()],
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame($originalName, $store->fresh()->name);
        $this->assertSame(5, $store->pictures()->count());
    }

    public function test_direct_application_retry_reuses_one_request_and_rejects_changed_payload(): void
    {
        $owner = User::factory()->provider()->create();
        $payload = $this->applicationPayload();
        $key = (string) Str::uuid();
        $this->actingAs($owner, 'sanctum');
        $first = $this->post('/api/provider/store-requests/direct', [...$payload,
            'idempotency_key' => $key, 'commercial_registration_picture' => $this->image(),
        ], ['Accept' => 'application/json'])->assertCreated();
        $second = $this->post('/api/provider/store-requests/direct', [...$payload,
            'idempotency_key' => $key, 'commercial_registration_picture' => $this->image(),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, StoreRequest::count());
        $this->assertCount(1, Storage::disk('store_management_tests')->allFiles());
        $this->post('/api/provider/store-requests/direct', [...$payload, 'name' => 'Changed',
            'idempotency_key' => $key, 'commercial_registration_picture' => $this->image(),
        ], ['Accept' => 'application/json'])->assertConflict();
        $this->post('/api/provider/store-requests/direct', [...$payload,
            'idempotency_key' => (string) Str::uuid(), 'commercial_registration_picture' => $this->image(),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('mobile');
    }

    public function test_provider_can_correct_only_their_rejected_application_and_keep_document(): void
    {
        $owner = User::factory()->provider()->create();
        $payload = $this->applicationPayload();
        $this->actingAs($owner, 'sanctum');
        $id = $this->post('/api/provider/store-requests/direct', [...$payload,
            'commercial_registration_picture' => $this->image(),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $application = StoreRequest::findOrFail($id);
        $original = $application->commercial_registration_path;
        unset($payload['mobile']);
        $this->postJson("/api/provider/store-requests/{$id}/resubmit", $payload)->assertConflict();
        $application->update(['request_status' => 'rejected', 'rejection_reason' => 'Correct details']);
        $this->postJson("/api/provider/store-requests/{$id}/resubmit", [...$payload, 'name' => 'Corrected'])
            ->assertOk()->assertJsonPath('data.request_status', 'pending')->assertJsonPath('data.rejection_reason', null);
        $this->assertSame($original, $application->fresh()->commercial_registration_path);
        $this->actingAs(User::factory()->provider()->create(), 'sanctum')
            ->getJson("/api/provider/store-requests/{$id}")->assertForbidden();
    }

    public function test_profile_rejects_blank_name_and_keeps_account_identity(): void
    {
        $owner = User::factory()->provider()->create();
        $this->actingAs($owner, 'sanctum')->putJson('/api/provider/profile', ['full_name' => '  '])
            ->assertUnprocessable()->assertJsonValidationErrors('full_name');
        $this->putJson('/api/provider/profile', ['full_name' => 'Updated', 'mobile' => '+966588888888', 'type' => 'customer'])
            ->assertOk()->assertJsonPath('data.mobile', $owner->mobile)->assertJsonPath('data.type', 'service provider');
        $this->actingAs(User::factory()->customer()->create(), 'sanctum')
            ->putJson('/api/provider/profile', ['full_name' => 'Unauthorized'])->assertForbidden();
    }

    private function applicationPayload(): array
    {
        return ['mobile' => '+966511223344', 'name' => 'New Store', 'nick_name' => 'New',
            'employee_name' => 'Manager', 'url_location' => 'https://maps.example.com/store',
            'commercial_registration_number' => '1234567890', 'city_id' => City::factory()->create()->id];
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAF/gL+9/3K8QAAAABJRU5ErkJggg=='));
    }
}
