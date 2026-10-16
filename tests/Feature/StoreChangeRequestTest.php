<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CarCompany;
use App\Models\Store;
use App\Models\StoreRequest;
use App\Models\User;
use App\Services\StoreChangeRequestService;
use App\Services\StoreMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['images.disk' => 'store_changes_tests']);
        Storage::fake('store_changes_tests');
    }

    private function store(): Store
    {
        $owner = User::factory()->provider()->create();
        $this->actingAs($owner, 'sanctum');

        return Store::factory()->create(['user_id' => $owner->id, 'name' => 'Approved store']);
    }

    private function payload(Store $store, array $extra = []): array
    {
        return [...[
            'name' => 'Proposed store', 'change_reason' => 'We changed our trading name.',
            'base_version' => StoreChangeRequestService::version($store->fresh()),
            'idempotency_key' => (string) Str::uuid(),
        ], ...$extra];
    }

    private function submit(Store $store, array $extra = [])
    {
        return $this->post("/api/provider/stores/{$store->id}/change-requests", $this->payload($store, $extra), ['Accept' => 'application/json']);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAF/gL+9/3K8QAAAABJRU5ErkJggg=='));
    }

    public function test_submission_is_a_proposal_and_admin_updates_same_store_without_changing_identity_or_status(): void
    {
        $store = $this->store();
        $store->update(['status' => 'inactive']);
        $company = CarCompany::factory()->create();
        $response = $this->submit($store, ['company_ids' => [$company->id], 'user_id' => 9999, 'status' => 'active'])
            ->assertCreated()->assertJsonPath('data.request_type', 'store_change')
            ->assertJsonPath('data.store_id', $store->id)->assertJsonPath('data.request_status', 'pending');
        $id = $response->json('data.id');
        $this->assertSame('Approved store', $store->fresh()->name);
        $this->assertSame(0, $store->companies()->count());
        $this->getJson("/api/provider/stores/{$store->id}")->assertJsonPath('data.pending_change_request_id', $id);
        $this->actingAs(Admin::factory()->create(), 'sanctum')->getJson("/api/admin/store-requests/{$id}")
            ->assertOk()->assertJsonPath('data.previous_details.name', 'Approved store')
            ->assertJsonPath('data.change_reason', 'We changed our trading name.');
        $this->postJson("/api/admin/store-requests/{$id}/accept")->assertOk()->assertJsonPath('data.request_status', 'accepted');
        $this->assertDatabaseCount('stores', 1);
        $this->assertDatabaseHas('stores', ['id' => $store->id, 'name' => 'Proposed store', 'mobile' => $store->mobile, 'user_id' => $store->user_id, 'status' => 'inactive']);
        $this->assertSame([$company->id], $store->fresh()->companies->modelKeys());
        $this->assertNull(StoreRequest::find($id)->pending_store_id);
        $this->assertNotNull(StoreRequest::find($id)->processed_at);
        $this->postJson("/api/admin/store-requests/{$id}/accept")->assertUnprocessable();
        $this->postJson("/api/admin/store-requests/{$id}/reject", ['reason' => 'Too late'])->assertUnprocessable();
    }

    public function test_reason_and_mobile_are_enforced_on_both_new_and_legacy_routes(): void
    {
        $store = $this->store();
        foreach (["/api/provider/stores/{$store->id}/change-requests", "/api/provider/store/{$store->id}"] as $url) {
            $payload = $this->payload($store, ['mobile' => '+966599999999', 'change_reason' => ' ']);
            $response = str_ends_with($url, 'change-requests') ? $this->postJson($url, $payload) : $this->putJson($url, $payload);
            $response->assertUnprocessable()->assertJsonValidationErrors(['mobile', 'change_reason']);
        }
        $this->submit($store, ['change_reason' => 'short'])->assertUnprocessable()->assertJsonValidationErrors('change_reason');
        $this->submit($store, ['base_version' => null, 'idempotency_key' => null])->assertUnprocessable()->assertJsonValidationErrors(['base_version', 'idempotency_key']);
        $this->assertDatabaseCount('store_requests', 0);
        $this->assertSame($store->mobile, $store->fresh()->mobile);
    }

    public function test_retry_is_idempotent_and_only_one_pending_change_per_store_is_allowed(): void
    {
        $store = $this->store();
        $payload = $this->payload($store);
        $url = "/api/provider/stores/{$store->id}/change-requests";
        $id = $this->postJson($url, $payload)->assertCreated()->json('data.id');
        $this->postJson($url, $payload)->assertCreated()->assertJsonPath('data.id', $id);
        $this->postJson($url, [...$payload, 'name' => 'Different'])->assertConflict();
        $this->submit($store)->assertConflict();
        $this->assertDatabaseCount('store_requests', 1);
        $this->getJson('/api/provider/store-requests')->assertJsonPath('data.0.idempotency_key', $payload['idempotency_key']);
        $this->actingAs(Admin::factory()->create(), 'sanctum')->postJson("/api/admin/store-requests/{$id}/accept")->assertOk();
        $this->actingAs($store->owner, 'sanctum')->postJson($url, $payload)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('store_requests', 1);
    }

    public function test_rejection_keeps_live_data_and_history_and_allows_a_fresh_request(): void
    {
        $store = $this->store();
        $id = $this->submit($store)->assertCreated()->json('data.id');
        $this->actingAs(Admin::factory()->create(), 'sanctum')->postJson("/api/admin/store-requests/{$id}/reject", ['reason' => 'Please explain the new name.'])
            ->assertOk()->assertJsonPath('data.rejection_reason', 'Please explain the new name.');
        $this->assertSame('Approved store', $store->fresh()->name);
        $this->actingAs($store->owner, 'sanctum');
        $next = $this->submit($store)->assertCreated()->json('data.id');
        $this->assertNotSame($id, $next);
        $this->assertDatabaseHas('store_requests', ['id' => $id, 'request_status' => 'rejected']);
        $this->assertDatabaseCount('store_requests', 2);
    }

    public function test_media_is_copied_into_private_proposal_then_applied_only_on_approval(): void
    {
        $store = $this->store();
        app(StoreMediaService::class)->update($store, ['commercial_registration_picture' => $this->image(), 'pictures' => [$this->image(), $this->image()]]);
        $store->refresh();
        [$removed, $kept] = $store->pictures->all();
        $oldRegistration = $store->commercial_registration_path;
        $id = $this->submit($store, ['retained_picture_ids' => [$kept->id], 'pictures' => [$this->image()], 'commercial_registration_picture' => $this->image()])
            ->assertCreated()->assertJsonCount(2, 'data.pictures')->json('data.id');
        $proposal = StoreRequest::findOrFail($id);
        $this->assertSame([$removed->id, $kept->id], $store->fresh()->pictures->modelKeys());
        $this->assertSame($oldRegistration, $store->fresh()->commercial_registration_path);
        $this->assertNotSame($kept->path, $proposal->pictures->first()->path);
        Storage::disk('store_changes_tests')->assertExists([$oldRegistration, $removed->path, $kept->path]);
        $this->actingAs(Admin::factory()->create(), 'sanctum')->postJson("/api/admin/store-requests/{$id}/accept")->assertOk();
        $store->refresh();
        $this->assertCount(2, $store->pictures);
        Storage::disk('store_changes_tests')->assertMissing([$oldRegistration, $removed->path, $kept->path]);
        $livePaths = [$store->commercial_registration_path, ...$store->pictures->pluck('path')->all()];
        $proposal->forceDelete();
        Storage::disk('store_changes_tests')->assertExists($livePaths);
    }

    public function test_proposed_photos_and_documents_are_only_visible_to_owner_and_active_admin(): void
    {
        $store = $this->store();
        $response = $this->submit($store, ['commercial_registration_picture' => $this->image(), 'pictures' => [$this->image()]])->assertCreated();
        $urls = [$response->json('data.pictures.0.url'), $response->json('data.commercial_registration_picture')];
        foreach ($urls as $url) {
            $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        }
        $id = $response->json('data.id');
        foreach ([User::factory()->provider()->create(), User::factory()->customer()->create(), Admin::factory()->blocked()->create()] as $outsider) {
            foreach ($urls as $url) {
                $this->actingAs($outsider, 'sanctum')->get($url)->assertForbidden();
            }
        }
        $this->actingAs(Admin::factory()->create(), 'sanctum');
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
        $other = StoreRequest::factory()->create();
        $this->get(str_replace("store-requests/{$id}/", "store-requests/{$other->id}/", $urls[0]))->assertNotFound();
        $this->assertArrayNotHasKey('disk', $response->json('data.pictures.0'));
        $this->assertArrayNotHasKey('path', $response->json('data.pictures.0'));
    }

    public function test_ownership_and_roles_cannot_be_bypassed(): void
    {
        $store = $this->store();
        foreach ([User::factory()->provider()->create(), User::factory()->customer()->create()] as $outsider) {
            $this->actingAs($outsider, 'sanctum');
            $this->submit($store)->assertForbidden();
        }
        $this->actingAs($store->owner, 'sanctum');
        $id = $this->submit($store)->assertCreated()->json('data.id');
        $this->postJson("/api/admin/store-requests/{$id}/accept")->assertForbidden();
        $this->assertSame('Approved store', $store->fresh()->name);
    }

    public function test_stale_form_and_stale_admin_approval_cannot_overwrite_newer_store_data(): void
    {
        $store = $this->store();
        $stalePayload = $this->payload($store);
        $store->update(['employee_name' => 'Updated manager']);
        $this->postJson("/api/provider/stores/{$store->id}/change-requests", $stalePayload)->assertConflict();
        $id = $this->submit($store)->assertCreated()->json('data.id');
        $store->update(['name' => 'Newer approved value']);
        $this->actingAs(Admin::factory()->create(), 'sanctum')->postJson("/api/admin/store-requests/{$id}/accept")->assertConflict();
        $this->assertSame('Newer approved value', $store->fresh()->name);
        $this->assertDatabaseHas('store_requests', ['id' => $id, 'request_status' => 'pending']);
    }

    public function test_failure_during_approval_rolls_back_data_and_cleans_only_new_copies(): void
    {
        $store = $this->store();
        app(StoreMediaService::class)->update($store, ['pictures' => [$this->image()]]);
        $id = $this->submit($store, ['commercial_registration_picture' => $this->image()])->assertCreated()->json('data.id');
        $before = Storage::disk('store_changes_tests')->allFiles();
        DB::unprepared("CREATE TRIGGER fail_change_accept BEFORE UPDATE ON store_requests BEGIN SELECT RAISE(ABORT, 'Simulated approval failure'); END");
        try {
            $this->actingAs(Admin::factory()->create(), 'sanctum')->postJson("/api/admin/store-requests/{$id}/accept")->assertStatus(500);
        } finally {
            DB::unprepared('DROP TRIGGER fail_change_accept');
        }
        $this->assertSame('Approved store', $store->fresh()->name);
        $this->assertDatabaseHas('store_requests', ['id' => $id, 'request_status' => 'pending', 'pending_store_id' => $store->id]);
        $this->assertSame($before, Storage::disk('store_changes_tests')->allFiles());
        $this->assertDatabaseCount('pending_image_deletions', 0);
    }

    public function test_identical_photo_submission_can_be_reconciled_after_approval_replaced_old_photo_ids(): void
    {
        $store = $this->store();
        app(StoreMediaService::class)->update($store, ['pictures' => [$this->image()]]);
        $payload = $this->payload($store, ['retained_picture_ids' => $store->pictures()->pluck('id')->all(), 'pictures' => [$this->image()]]);
        $url = "/api/provider/stores/{$store->id}/change-requests";
        $id = $this->post($url, $payload, ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->actingAs(Admin::factory()->create(), 'sanctum')->postJson("/api/admin/store-requests/{$id}/accept")->assertOk();
        $this->actingAs($store->owner, 'sanctum')->post($url, $payload, ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.id', $id)->assertJsonPath('data.request_status', 'accepted');
        $this->assertDatabaseCount('store_requests', 1);
    }

    public function test_failed_proposal_creation_removes_copies_and_preserves_live_store(): void
    {
        $store = $this->store();
        app(StoreMediaService::class)->update($store, ['commercial_registration_picture' => $this->image(), 'pictures' => [$this->image()]]);
        $before = Storage::disk('store_changes_tests')->allFiles();
        DB::unprepared("CREATE TRIGGER fail_proposal_photo BEFORE INSERT ON store_request_pictures BEGIN SELECT RAISE(ABORT, 'Simulated proposal failure'); END");
        try {
            $this->submit($store)->assertStatus(500);
        } finally {
            DB::unprepared('DROP TRIGGER fail_proposal_photo');
        }
        $this->assertDatabaseCount('store_requests', 0);
        $this->assertSame('Approved store', $store->fresh()->name);
        $this->assertSame($before, Storage::disk('store_changes_tests')->allFiles());
    }

    public function test_rejected_change_cannot_be_resubmitted_in_place_or_disguised_as_new_store(): void
    {
        $store = $this->store();
        $id = $this->submit($store)->assertCreated()->json('data.id');
        $this->actingAs(Admin::factory()->create(), 'sanctum')->postJson("/api/admin/store-requests/{$id}/reject", ['reason' => 'Please clarify the change.'])->assertOk();
        $data = $store->only(['name', 'nick_name', 'employee_name', 'url_location', 'commercial_registration_number', 'city_id']);
        $this->actingAs($store->owner, 'sanctum')->postJson("/api/provider/store-requests/{$id}/resubmit", $data)->assertConflict();
        $this->post('/api/provider/store-requests/direct', [...$data, 'mobile' => $store->mobile,
            'commercial_registration_picture' => $this->image()], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('mobile');
        $this->assertDatabaseHas('store_requests', ['id' => $id, 'request_status' => 'rejected']);
        $this->assertDatabaseCount('stores', 1);
    }

    public function test_invalid_gallery_or_unchanged_payload_never_creates_a_request(): void
    {
        $store = $this->store();
        app(StoreMediaService::class)->update($store, ['pictures' => array_map(fn () => $this->image(), range(1, 5))]);
        $this->submit($store, ['pictures' => [$this->image()]])->assertUnprocessable()->assertJsonValidationErrors('pictures');
        $this->submit($store, ['name' => 'Approved store'])->assertUnprocessable()->assertJsonValidationErrors('change_reason');
        $this->submit($store, ['retained_picture_ids' => [999999]])->assertUnprocessable()->assertJsonValidationErrors('retained_picture_ids.0');
        $this->assertDatabaseCount('store_requests', 0);
        $this->assertSame(5, $store->pictures()->count());
    }
}
