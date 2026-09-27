<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Store;
use App\Models\StoreRequest;
use App\Models\User;
use App\Services\AdminStoreRequestService;
use App\Services\ImageStorageService;
use App\Services\OtpService;
use App\Services\StoreCarService;
use App\Services\StoreMediaService;
use App\Services\StoreRequestService;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class StoreMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['images.disk' => 'store_media_tests']);
        Storage::fake('store_media_tests');
    }

    public function test_provider_uploads_registration_and_gallery_with_private_metadata(): void
    {
        [$provider, $store] = $this->owner();

        $response = $this->actingAs($provider, 'sanctum')
            ->post("/api/provider/store/{$store->id}", [
                '_method' => 'PUT',
                'commercial_registration_picture' => $this->image(),
                'pictures' => [$this->image(), $this->image()],
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonCount(2, 'data.pictures')
            ->assertJsonPath('data.pictures.0.mime_type', 'image/png')
            ->assertJsonPath('data.pictures.0.sort_order', 0)
            ->assertJsonPath('data.pictures.1.sort_order', 1);

        $store->refresh();
        $this->assertTrue($store->hasRegistrationImage());
        Storage::disk('store_media_tests')->assertExists($store->commercial_registration_path);
        $this->assertSame(['id', 'url', 'mime_type', 'size_bytes', 'sort_order'], array_keys($response->json('data.pictures.0')));
        $this->assertArrayNotHasKey('commercial_registration_path', $store->toArray());
        $this->assertArrayNotHasKey('commercial_registration_disk', $store->toArray());

        foreach ($store->pictures as $picture) {
            Storage::disk('store_media_tests')->assertExists($picture->path);
            $this->assertArrayNotHasKey('path', $picture->toArray());
            $this->assertArrayNotHasKey('disk', $picture->toArray());
        }

        $this->get($response->json('data.commercial_registration_picture'))
            ->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_updates_reject_image_strings_non_images_and_excess_gallery_files(): void
    {
        [$provider, $store] = $this->owner();
        $this->actingAs($provider, 'sanctum');

        $this->putJson("/api/provider/store/{$store->id}", [
            'commercial_registration_picture' => 'uploads/reg.jpg',
            'pictures' => ['https://example.com/image.png'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['commercial_registration_picture', 'pictures.0']);

        $this->post("/api/provider/store/{$store->id}", [
            '_method' => 'PUT',
            'commercial_registration_picture' => UploadedFile::fake()->create('document.pdf', 2, 'application/pdf'),
            'pictures' => array_fill(0, 6, $this->image()),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors(['commercial_registration_picture', 'pictures']);

        $this->assertSame([], Storage::disk('store_media_tests')->allFiles());
    }

    public function test_replacement_and_gallery_clear_remove_old_files_only_after_commit(): void
    {
        [$provider, $store] = $this->owner();
        $media = app(StoreMediaService::class);
        $store = $media->update($store, [
            'commercial_registration_picture' => $this->image(),
            'pictures' => [$this->image()],
        ]);
        $oldRegistration = $store->commercial_registration_path;
        $oldPicture = $store->pictures()->firstOrFail();

        $this->actingAs($provider, 'sanctum')->putJson("/api/provider/store/{$store->id}", ['name' => 'Still here'])->assertOk();
        Storage::disk('store_media_tests')->assertExists($oldRegistration);
        Storage::disk('store_media_tests')->assertExists($oldPicture->path);

        $this->post("/api/provider/store/{$store->id}", [
            '_method' => 'PUT',
            'commercial_registration_picture' => $this->image(),
            'pictures' => [$this->image()],
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonCount(1, 'data.pictures');

        Storage::disk('store_media_tests')->assertMissing($oldRegistration);
        Storage::disk('store_media_tests')->assertMissing($oldPicture->path);
        $newPicture = $store->fresh()->pictures()->firstOrFail();

        $this->putJson("/api/provider/store/{$store->id}", ['pictures' => []])->assertOk()->assertJsonCount(0, 'data.pictures');
        Storage::disk('store_media_tests')->assertMissing($newPicture->path);
        $this->assertCount(1, Storage::disk('store_media_tests')->allFiles());
        $this->assertDatabaseCount('pending_image_deletions', 0);
    }

    public function test_failed_replacement_rolls_back_metadata_and_removes_new_uploads(): void
    {
        [$provider, $store] = $this->owner();
        $store = app(StoreMediaService::class)->update($store, [
            'commercial_registration_picture' => $this->image(),
            'pictures' => [$this->image()],
        ]);
        $oldFiles = Storage::disk('store_media_tests')->allFiles();
        $oldPicture = $store->pictures()->firstOrFail();
        DB::unprepared("CREATE TRIGGER fail_store_picture_insert BEFORE INSERT ON store_pictures BEGIN SELECT RAISE(ABORT, 'Simulated image metadata failure'); END");

        try {
            $this->actingAs($provider, 'sanctum')->post("/api/provider/store/{$store->id}", [
                '_method' => 'PUT',
                'name' => 'Rolled Back',
                'commercial_registration_picture' => $this->image(),
                'pictures' => [$this->image()],
            ], ['Accept' => 'application/json'])->assertStatus(500);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_store_picture_insert');
        }

        $this->assertNotSame('Rolled Back', $store->fresh()->name);
        $this->assertSame($store->commercial_registration_path, $store->fresh()->commercial_registration_path);
        $this->assertSame($oldPicture->id, $store->fresh()->pictures()->firstOrFail()->id);
        $this->assertSame($oldFiles, Storage::disk('store_media_tests')->allFiles());
    }

    public function test_failed_onboarding_cleans_upload_and_keeps_customer_role(): void
    {
        $customer = User::factory()->customer()->create();
        $payload = $this->requestPayload($customer);
        $payload['temp_token'] = app(OtpService::class)->createPendingToken('store', $payload['mobile']);
        DB::unprepared("CREATE TRIGGER fail_registration_insert BEFORE INSERT ON store_requests BEGIN SELECT RAISE(ABORT, 'Simulated registration metadata failure'); END");

        try {
            $this->actingAs($customer, 'sanctum')->post('/api/provider/store-requests', $payload, ['Accept' => 'application/json'])->assertStatus(500);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_registration_insert');
        }

        $this->assertDatabaseCount('store_requests', 0);
        $this->assertSame('customer', $customer->fresh()->type->value);
        $this->assertSame([], Storage::disk('store_media_tests')->allFiles());
    }

    public function test_approval_copies_registration_and_independent_deletion_preserves_other_owner(): void
    {
        $provider = User::factory()->provider()->create();
        $request = app(StoreRequestService::class)->createForProvider($provider, $this->requestPayload($provider));
        $store = app(AdminStoreRequestService::class)->accept($request, Admin::factory()->create());

        $this->assertNotSame($request->commercial_registration_path, $store->commercial_registration_path);
        $this->assertSame(
            Storage::disk('store_media_tests')->get($request->commercial_registration_path),
            Storage::disk('store_media_tests')->get($store->commercial_registration_path),
        );

        $request->forceDelete();
        Storage::disk('store_media_tests')->assertMissing($request->commercial_registration_path);
        Storage::disk('store_media_tests')->assertExists($store->commercial_registration_path);
        $store->delete();
        Storage::disk('store_media_tests')->assertExists($store->commercial_registration_path);
        $store->forceDelete();
        $this->assertSame([], Storage::disk('store_media_tests')->allFiles());
    }

    public function test_approval_failure_removes_copy_but_preserves_request_document(): void
    {
        $provider = User::factory()->provider()->create();
        $request = app(StoreRequestService::class)->createForProvider($provider, $this->requestPayload($provider));
        DB::unprepared("CREATE TRIGGER fail_request_approval BEFORE UPDATE ON store_requests BEGIN SELECT RAISE(ABORT, 'Simulated approval persistence failure'); END");

        try {
            $this->actingAs(Admin::factory()->create(), 'sanctum')->postJson("/api/admin/store-requests/{$request->id}/accept")->assertStatus(500);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_request_approval');
        }

        $this->assertSame('pending', $request->fresh()->request_status->value);
        $this->assertSame([$request->commercial_registration_path], Storage::disk('store_media_tests')->allFiles());
        $this->assertDatabaseCount('stores', 0);
    }

    public function test_registration_cleanup_failure_keeps_retry_reference_without_losing_new_image(): void
    {
        [, $store] = $this->owner();
        $store = app(StoreMediaService::class)->update($store, ['commercial_registration_picture' => $this->image()]);
        $oldPath = $store->commercial_registration_path;
        $images = Mockery::mock(ImageStorageService::class, [app(FilesystemFactory::class)])->makePartial();
        $images->shouldReceive('delete')->once()->andThrow(new RuntimeException('Storage unavailable'));

        $updated = (new StoreMediaService($images, app(StoreCarService::class)))->update($store, ['commercial_registration_picture' => $this->image()]);

        $this->assertNotSame($oldPath, $updated->commercial_registration_path);
        Storage::disk('store_media_tests')->assertExists($updated->commercial_registration_path);
        $this->assertDatabaseHas('pending_image_deletions', ['disk' => 'store_media_tests', 'path' => $oldPath]);

        app(ImageStorageService::class)->retryPendingDeletions();
        Storage::disk('store_media_tests')->assertMissing($oldPath);
        Storage::disk('store_media_tests')->assertExists($updated->commercial_registration_path);
        $this->assertDatabaseCount('pending_image_deletions', 0);
    }

    public function test_untrusted_legacy_images_are_hidden_from_responses(): void
    {
        [$provider, $store] = $this->owner();
        $store->pictures()->create(['path' => 'https://example.com/legacy.png']);
        $request = StoreRequest::factory()->create(['user_id' => $provider->id]);

        $this->actingAs($provider, 'sanctum')->getJson('/api/provider/stores')
            ->assertOk()->assertJsonCount(0, 'data.0.pictures')
            ->assertJsonPath('data.0.commercial_registration_picture', null);
        $this->getJson("/api/provider/store-requests/{$request->id}")
            ->assertOk()->assertJsonPath('data.commercial_registration_picture', null);
    }

    private function owner(): array
    {
        $provider = User::factory()->provider()->create();

        return [$provider, Store::factory()->create(['user_id' => $provider->id])];
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAF/gL+9/3K8QAAAABJRU5ErkJggg=='
        ));
    }

    private function requestPayload(User $provider): array
    {
        return [
            'name' => 'Store with images',
            'mobile' => $provider->mobile === '+966555555555' ? '+966555555556' : '+966555555555',
            'nick_name' => 'Store',
            'employee_name' => 'Employee',
            'url_location' => 'https://maps.example.com/store',
            'commercial_registration_number' => '1234567890',
            'commercial_registration_picture' => $this->image(),
            'city_id' => $provider->city_id,
        ];
    }
}
