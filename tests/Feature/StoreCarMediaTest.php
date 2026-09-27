<?php

namespace Tests\Feature;

use App\Models\CarName;
use App\Models\CarSection;
use App\Models\Color;
use App\Models\FuelType;
use App\Models\Store;
use App\Models\StoreCarPicture;
use App\Models\StoresCar;
use App\Models\User;
use App\Services\ImageStorageService;
use App\Services\StoreCarService;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class StoreCarMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['images.disk' => 'images_local']);
        Storage::fake('images_local');
    }

    public function test_store_car_uploads_are_private_and_return_the_shared_picture_shape(): void
    {
        [$provider, $store] = $this->owner();

        $response = $this->actingAs($provider, 'sanctum')
            ->post("/api/provider/store/{$store->id}/cars", $this->payload([
                'pictures' => [$this->image(), $this->image()],
            ]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonCount(2, 'data.pictures')
            ->assertJsonPath('data.pictures.0.mime_type', 'image/png')
            ->assertJsonPath('data.pictures.0.sort_order', 0)
            ->assertJsonPath('data.pictures.1.sort_order', 1);

        $car = StoresCar::findOrFail($response->json('data.id'));
        $picture = $car->pictures()->firstOrFail();
        Storage::disk('images_local')->assertExists($picture->path);
        $this->assertStringStartsWith("store-cars/{$store->id}/{$car->id}/", $picture->path);
        $this->assertSame(['id', 'url', 'mime_type', 'size_bytes', 'sort_order'], array_keys($response->json('data.pictures.0')));
        $this->assertArrayNotHasKey('path', $picture->toArray());
        $this->assertArrayNotHasKey('disk', $picture->toArray());

        $this->get($response->json('data.pictures.0.url'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_store_car_create_and_update_reject_arbitrary_paths_non_images_and_too_many_files(): void
    {
        [$provider, $store] = $this->owner();
        $car = StoresCar::factory()->create(['store_id' => $store->id]);
        $this->actingAs($provider, 'sanctum');

        $this->postJson("/api/provider/store/{$store->id}/cars", $this->payload([
            'pictures' => ['https://example.com/car.jpg'],
        ]))->assertUnprocessable()->assertJsonValidationErrors('pictures.0');

        $this->putJson("/api/provider/store/{$store->id}/cars/{$car->id}", [
            'pictures' => ['../../private.png'],
        ])->assertUnprocessable()->assertJsonValidationErrors('pictures.0');

        $this->post("/api/provider/store/{$store->id}/cars/{$car->id}", [
            '_method' => 'PUT',
            'pictures' => [UploadedFile::fake()->create('document.pdf', 1, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('pictures.0');

        $this->post("/api/provider/store/{$store->id}/cars/{$car->id}", [
            '_method' => 'PUT',
            'pictures' => array_fill(0, 6, $this->image()),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('pictures');

        $this->assertSame([], Storage::disk('images_local')->allFiles());
    }

    public function test_replacement_deletes_old_files_after_commit_and_omitted_pictures_are_preserved(): void
    {
        [, $store] = $this->owner();
        $service = app(StoreCarService::class);
        $car = $service->create($store, $this->payload(['pictures' => [$this->image()]]));
        $oldPicture = $car->pictures()->firstOrFail();

        $service->update($car, ['vehicle_plat_number' => 'CHANGED']);
        $this->assertSame($oldPicture->id, $car->pictures()->firstOrFail()->id);
        Storage::disk('images_local')->assertExists($oldPicture->path);

        $service->update($car, ['pictures' => [$this->image()]]);
        $newPicture = $car->pictures()->firstOrFail();
        $this->assertNotSame($oldPicture->id, $newPicture->id);
        Storage::disk('images_local')->assertMissing($oldPicture->path);
        Storage::disk('images_local')->assertExists($newPicture->path);
        $this->assertSoftDeleted('store_car_pictures', ['id' => $oldPicture->id]);

        $service->update($car, ['pictures' => []]);
        Storage::disk('images_local')->assertMissing($newPicture->path);
        $this->assertSame(0, $car->pictures()->count());
    }

    public function test_failed_replacement_preserves_old_file_and_removes_new_uploads(): void
    {
        [$provider, $store] = $this->owner();
        $car = app(StoreCarService::class)->create($store, $this->payload(['pictures' => [$this->image()]]));
        $oldPicture = $car->pictures()->firstOrFail();
        DB::unprepared("CREATE TRIGGER fail_store_car_picture_insert BEFORE INSERT ON store_car_pictures BEGIN SELECT RAISE(ABORT, 'Simulated picture persistence failure'); END");

        try {
            $this->actingAs($provider, 'sanctum')
                ->post("/api/provider/store/{$store->id}/cars/{$car->id}", [
                    '_method' => 'PUT',
                    'vehicle_plat_number' => 'ROLLED-BACK',
                    'pictures' => [$this->image()],
                ], ['Accept' => 'application/json'])->assertStatus(500);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_store_car_picture_insert');
        }

        $this->assertSame($oldPicture->id, $car->fresh()->pictures()->firstOrFail()->id);
        $this->assertNotSame('ROLLED-BACK', $car->fresh()->vehicle_plat_number);
        $this->assertSame([$oldPicture->path], Storage::disk('images_local')->allFiles());
    }

    public function test_failed_initial_picture_persistence_removes_uploaded_files(): void
    {
        [$provider, $store] = $this->owner();
        DB::unprepared("CREATE TRIGGER fail_store_car_picture_insert BEFORE INSERT ON store_car_pictures BEGIN SELECT RAISE(ABORT, 'Simulated picture persistence failure'); END");

        try {
            $this->actingAs($provider, 'sanctum')
                ->post("/api/provider/store/{$store->id}/cars", $this->payload([
                    'pictures' => [$this->image()],
                ]), ['Accept' => 'application/json'])->assertStatus(500);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_store_car_picture_insert');
        }

        $this->assertDatabaseCount('stores_cars', 0);
        $this->assertDatabaseCount('store_car_pictures', 0);
        $this->assertSame([], Storage::disk('images_local')->allFiles());
    }

    public function test_replacement_cleanup_failure_keeps_inaccessible_metadata_for_retry(): void
    {
        [, $store] = $this->owner();
        $car = app(StoreCarService::class)->create($store, $this->payload(['pictures' => [$this->image()]]));
        $oldPicture = $car->pictures()->firstOrFail();
        $storage = Mockery::mock(ImageStorageService::class, [app(FilesystemFactory::class)])->makePartial();
        $storage->shouldReceive('delete')->once()->andThrow(new RuntimeException('Deletion unavailable'));

        (new StoreCarService($storage))->update($car, ['pictures' => []]);

        $this->assertSoftDeleted('store_car_pictures', ['id' => $oldPicture->id]);
        $this->assertSame(0, $car->pictures()->count());
        Storage::disk('images_local')->assertExists($oldPicture->path);
        $this->assertDatabaseHas('pending_image_deletions', ['disk' => $oldPicture->disk, 'path' => $oldPicture->path]);

        app(ImageStorageService::class)->retryPendingDeletions();
        Storage::disk('images_local')->assertMissing($oldPicture->path);
        $this->assertDatabaseCount('pending_image_deletions', 0);
    }

    public function test_soft_delete_preserves_files_and_force_delete_removes_all_picture_files(): void
    {
        [, $store] = $this->owner();
        $car = app(StoreCarService::class)->create($store, $this->payload([
            'pictures' => [$this->image(), $this->image()],
        ]));
        $pictures = $car->pictures()->get();
        $pictures->last()->delete();

        $car->delete();
        foreach ($pictures as $picture) {
            Storage::disk('images_local')->assertExists($picture->path);
        }

        $car->forceDelete();
        foreach ($pictures as $picture) {
            Storage::disk('images_local')->assertMissing($picture->path);
        }
        $this->assertDatabaseCount('store_car_pictures', 0);
    }

    public function test_legacy_picture_strings_are_not_exposed_as_uploaded_images(): void
    {
        [$provider, $store] = $this->owner();
        $car = StoresCar::factory()->create(['store_id' => $store->id]);
        StoreCarPicture::factory()->create([
            'car_id' => $car->id,
            'disk' => null,
            'path' => 'https://example.com/untrusted.jpg',
        ]);

        $this->actingAs($provider, 'sanctum')->getJson("/api/stores/{$store->id}/cars/{$car->id}")
            ->assertOk()->assertJsonCount(0, 'data.pictures');
    }

    private function owner(): array
    {
        $provider = User::factory()->provider()->create();

        return [$provider, Store::factory()->create(['user_id' => $provider->id])];
    }

    private function payload(array $overrides = []): array
    {
        return [
            'car_name_id' => CarName::factory()->create()->id,
            'manufacturing_year' => 2022,
            'vehicle_plat_number' => 'ABC-1234',
            'color_id' => Color::factory()->create()->id,
            'fuel_type' => FuelType::factory()->create()->id,
            'sections' => [['section_id' => CarSection::factory()->create()->id, 'condition' => 'okay']],
            ...$overrides,
        ];
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('car.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAF/gL+9/3K8QAAAABJRU5ErkJggg=='
        ));
    }
}
