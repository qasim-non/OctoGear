<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\CarName;
use App\Models\Color;
use App\Models\CustomerCar;
use App\Models\CustomerCarPicture;
use App\Models\FuelType;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class CustomerCarMediaTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAF/gL+9/3K8QAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'customer_car_media.disk' => 'customer_car_media_local',
            'customer_car_media.idempotency_retention_hours' => 24,
        ]);

        Storage::fake('customer_car_media_local');
    }

    public function test_customer_can_create_a_car_with_private_multipart_photos(): void
    {
        $customer = User::factory()->customer()->create();

        $response = $this->postCar($customer, [
            'pictures' => [$this->image()],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.vehicle_plat_number')
            ->assertJsonPath('data.pictures.0.mime_type', 'image/png')
            ->assertJsonPath('data.pictures.0.sort_order', 0);

        $car = CustomerCar::query()->findOrFail($response->json('data.id'));
        $this->assertFalse(Schema::hasColumn('customer_cars', 'vehicle_plat_number'));
        $picture = $car->pictures()->firstOrFail();
        $pictureData = $response->json('data.pictures.0');

        Storage::disk('customer_car_media_local')->assertExists($picture->path);
        $this->assertSame('customer_car_media_local', $picture->disk);
        $this->assertSame('/api/customer/customer-cars/'.$car->id.'/pictures/'.$picture->id, $pictureData['url']);
        $this->assertArrayNotHasKey('path', $pictureData);
        $this->assertArrayNotHasKey('disk', $pictureData);
        $this->assertArrayNotHasKey('original_name', $pictureData);
        $this->assertArrayNotHasKey('idempotency_key', $car->toArray());
        $this->assertArrayNotHasKey('idempotency_fingerprint', $car->toArray());
        $this->assertArrayNotHasKey('disk', $picture->toArray());
        $this->assertArrayNotHasKey('path', $picture->toArray());
    }

    public function test_creation_requires_a_uuid_idempotency_key_header(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'sanctum')
            ->post('/api/customer/customer-cars', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'not-a-uuid')
            ->post('/api/customer/customer-cars', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');
    }

    public function test_customer_photos_inherit_the_shared_disk_and_limits_without_an_override(): void
    {
        config([
            'customer_car_media.disk' => null,
            'customer_car_media.max_files' => null,
            'images.disk' => 'images_local',
            'images.max_files' => 1,
        ]);
        Storage::fake('images_local');
        $customer = User::factory()->customer()->create();

        $car = $this->createCar($customer, ['pictures' => [$this->image()]]);
        $picture = $car->pictures()->firstOrFail();
        $this->assertSame('images_local', $picture->disk);
        Storage::disk('images_local')->assertExists($picture->path);

        $this->actingAs($customer, 'sanctum')
            ->post('/api/customer/customer-cars/'.$car->id.'/pictures', ['pictures' => [$this->image()]])
            ->assertUnprocessable();
    }

    public function test_reusing_an_idempotency_key_returns_the_original_car_without_duplicate_photos(): void
    {
        $customer = User::factory()->customer()->create();
        $key = (string) Str::uuid();
        $payload = $this->payload(['pictures' => [$this->image()]]);

        $first = $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', $key)
            ->post('/api/customer/customer-cars', $payload)
            ->assertCreated();

        $retryPayload = $payload;
        $retryPayload['pictures'] = [$this->image()];

        $second = $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', $key)
            ->post('/api/customer/customer-cars', $retryPayload)
            ->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, CustomerCar::query()->count());
        $this->assertSame(1, CustomerCarPicture::query()->count());
    }

    public function test_reusing_an_idempotency_key_for_changed_multipart_content_returns_a_safe_conflict(): void
    {
        $customer = User::factory()->customer()->create();
        $key = (string) Str::uuid();
        $payload = $this->payload();

        $this->postCarWithKey($customer, $key, [
            ...$payload,
            'pictures' => [$this->image('first'), $this->image('second')],
        ])->assertCreated();

        // The same bytes in a different upload order are a different
        // multipart submission, not a legitimate replay.
        $this->postCarWithKey($customer, $key, [
            ...$payload,
            'pictures' => [$this->image('second'), $this->image('first')],
        ])
            ->assertConflict()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('auth.validation.idempotency_key.conflict'));

        // Scalar vehicle changes are also protected by the same fingerprint.
        $this->postCarWithKey($customer, $key, [
            ...$payload,
            'manufacturing_year' => 2023,
            'pictures' => [$this->image('first'), $this->image('second')],
        ])->assertConflict();

        $this->assertSame(1, CustomerCar::query()->count());
        $this->assertSame(2, CustomerCarPicture::query()->count());
    }

    public function test_a_soft_deleted_car_is_never_replayed_for_the_same_idempotency_key(): void
    {
        $customer = User::factory()->customer()->create();
        $key = (string) Str::uuid();
        $payload = $this->payload(['pictures' => [$this->image('original')]]);

        $first = $this->postCarWithKey($customer, $key, $payload)->assertCreated();
        $firstCar = CustomerCar::query()->findOrFail($first->json('data.id'));
        $firstCar->delete();

        $second = $this->postCarWithKey($customer, $key, $this->payload([
            'car_name_id' => $payload['car_name_id'],
            'color_id' => $payload['color_id'],
            'fuel_type' => $payload['fuel_type'],
            'pictures' => [$this->image('original')],
        ]))->assertCreated();

        $this->assertNotSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSoftDeleted('customer_cars', ['id' => $firstCar->id]);
        $this->assertDatabaseHas('customer_cars', [
            'id' => $firstCar->id,
            'idempotency_key' => null,
            'idempotency_fingerprint' => null,
        ]);
    }

    public function test_an_expired_idempotency_key_is_released_when_the_scheduler_has_not_run(): void
    {
        $customer = User::factory()->customer()->create();
        $key = (string) Str::uuid();
        $payload = $this->payload(['pictures' => [$this->image('original')]]);

        $first = $this->postCarWithKey($customer, $key, $payload)->assertCreated();
        $firstCar = CustomerCar::query()->findOrFail($first->json('data.id'));
        $firstCar->forceFill(['created_at' => now()->subHours(25)])->saveQuietly();

        $second = $this->postCarWithKey($customer, $key, $this->payload([
            'car_name_id' => $payload['car_name_id'],
            'color_id' => $payload['color_id'],
            'fuel_type' => $payload['fuel_type'],
            'pictures' => [$this->image('original')],
        ]))->assertCreated();

        $this->assertNotSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseHas('customer_cars', [
            'id' => $firstCar->id,
            'idempotency_key' => null,
            'idempotency_fingerprint' => null,
        ]);
    }

    public function test_scheduled_purge_releases_expired_server_only_idempotency_metadata(): void
    {
        $customer = User::factory()->customer()->create();
        $key = (string) Str::uuid();
        $car = $this->createCar($customer, ['pictures' => [$this->image()]], $key);
        $car->forceFill(['created_at' => now()->subHours(25)])->saveQuietly();

        $this->assertSame(0, Artisan::call('customer-car-media:purge-expired-idempotency-keys'));
        $this->assertDatabaseHas('customer_cars', [
            'id' => $car->id,
            'idempotency_key' => null,
            'idempotency_fingerprint' => null,
        ]);
    }

    public function test_customer_can_add_photos_and_the_total_limit_is_enforced(): void
    {
        $customer = User::factory()->customer()->create();
        $car = $this->createCar($customer, [
            'pictures' => [$this->image(), $this->image(), $this->image(), $this->image()],
        ]);

        $this->actingAs($customer, 'sanctum')
            ->post('/api/customer/customer-cars/'.$car->id.'/pictures', [
                'pictures' => [$this->image()],
            ])
            ->assertCreated()
            ->assertJsonCount(1, 'data');

        $this->actingAs($customer, 'sanctum')
            ->post('/api/customer/customer-cars/'.$car->id.'/pictures', [
                'pictures' => [$this->image()],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('pictures')
            ->assertJsonPath('errors.pictures.0', __('auth.validation.pictures.total_max', ['max' => 5]));

        $this->assertSame(5, $car->fresh()->pictures()->count());
    }

    public function test_photo_upload_rejects_untrusted_files_too_many_files_and_invalid_dimensions(): void
    {
        $customer = User::factory()->customer()->create();

        $this->postCar($customer, [
            'pictures' => [UploadedFile::fake()->create('not-a-photo.pdf', 10, 'application/pdf')],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pictures.0');

        $this->postCar($customer, [
            'pictures' => array_fill(0, 6, $this->image()),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pictures');

        config(['customer_car_media.min_width' => 2]);

        $this->postCar($customer, [
            'pictures' => [$this->image()],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pictures.0');
    }

    public function test_validation_messages_are_localized_for_arabic_requests(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Accept-Language', 'ar')
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->post('/api/customer/customer-cars', $this->payload([
                'pictures' => [UploadedFile::fake()->create('not-a-photo.pdf', 10, 'application/pdf')],
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'فشل التحقق');
    }

    public function test_only_the_owner_can_stream_a_private_photo_and_nested_mismatches_are_hidden(): void
    {
        $owner = User::factory()->customer()->create();
        $otherCustomer = User::factory()->customer()->create();
        $provider = User::factory()->provider()->create();
        $firstCar = $this->createCar($owner, ['pictures' => [$this->image()]]);
        $secondCar = $this->createCar($owner, ['pictures' => [$this->image()]]);
        $picture = $firstCar->pictures()->firstOrFail();
        $otherPicture = $secondCar->pictures()->firstOrFail();
        $url = '/api/customer/customer-cars/'.$firstCar->id.'/pictures/'.$picture->id;

        $this->actingAs($owner, 'sanctum')
            ->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($otherCustomer, 'sanctum')
            ->getJson($url)
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->getJson($url)
            ->assertUnauthorized();

        $this->actingAs($provider, 'sanctum')
            ->getJson($url)
            ->assertForbidden();

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/customer/customer-cars/'.$firstCar->id.'/pictures/'.$otherPicture->id)
            ->assertNotFound();
    }

    public function test_customer_can_delete_an_individual_photo_but_soft_deleted_cars_keep_private_media(): void
    {
        $customer = User::factory()->customer()->create();
        $car = $this->createCar($customer, ['pictures' => [$this->image(), $this->image()]]);
        $firstPicture = $car->pictures()->firstOrFail();
        $secondPicture = $car->pictures()->skip(1)->firstOrFail();
        $firstPath = $firstPicture->path;
        $secondPath = $secondPicture->path;

        $this->actingAs($customer, 'sanctum')
            ->deleteJson('/api/customer/customer-cars/'.$car->id.'/pictures/'.$firstPicture->id)
            ->assertOk();

        Storage::disk('customer_car_media_local')->assertMissing($firstPath);
        $this->assertDatabaseMissing('customer_car_pictures', ['id' => $firstPicture->id]);

        $this->actingAs($customer, 'sanctum')
            ->deleteJson('/api/customer/customer-cars/'.$car->id)
            ->assertOk();

        Storage::disk('customer_car_media_local')->assertExists($secondPath);
        $this->assertSoftDeleted('customer_cars', ['id' => $car->id]);
        $this->assertDatabaseHas('customer_car_pictures', ['id' => $secondPicture->id]);
    }

    public function test_force_deleting_a_car_removes_active_and_soft_deleted_private_media(): void
    {
        $customer = User::factory()->customer()->create();
        $car = $this->createCar($customer, [
            'pictures' => [$this->image('active'), $this->image('soft-deleted')],
        ]);
        $activePicture = $car->pictures()->firstOrFail();
        $softDeletedPicture = $car->pictures()->skip(1)->firstOrFail();
        $activePath = $activePicture->path;
        $softDeletedPath = $softDeletedPicture->path;

        $softDeletedPicture->delete();

        $car->forceDelete();

        Storage::disk('customer_car_media_local')->assertMissing($activePath);
        Storage::disk('customer_car_media_local')->assertMissing($softDeletedPath);
        $this->assertDatabaseMissing('customer_cars', ['id' => $car->id]);
        $this->assertDatabaseMissing('customer_car_pictures', ['id' => $activePicture->id]);
        $this->assertDatabaseMissing('customer_car_pictures', ['id' => $softDeletedPicture->id]);
    }

    public function test_force_delete_aborts_when_private_media_cleanup_fails(): void
    {
        $customer = User::factory()->customer()->create();
        $car = $this->createCar($customer, ['pictures' => [$this->image()]]);
        $picture = $car->pictures()->firstOrFail();

        $filesystem = Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('exists')->once()->with($picture->path)->andReturnTrue();
        $filesystem->shouldReceive('delete')->once()->with($picture->path)->andReturnFalse();

        $factory = Mockery::mock(FilesystemFactory::class);
        $factory->shouldReceive('disk')->once()->with($picture->disk)->andReturn($filesystem);
        $this->app->instance(FilesystemFactory::class, $factory);

        try {
            $car->forceDelete();
            $this->fail('Expected private media cleanup to abort the force delete.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame(503, $exception->statusCode());
        }

        $this->assertDatabaseHas('customer_cars', ['id' => $car->id]);
        $this->assertDatabaseHas('customer_car_pictures', ['id' => $picture->id]);
    }

    public function test_failed_picture_persistence_removes_files_written_before_the_database_rollback(): void
    {
        $customer = User::factory()->customer()->create();

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER fail_customer_car_picture_insert
            BEFORE INSERT ON customer_car_pictures
            BEGIN
                SELECT RAISE(ABORT, 'Simulated customer-car picture persistence failure');
            END;
            SQL);

        try {
            $this->postCar($customer, [
                'pictures' => [$this->image()],
            ])->assertStatus(500);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_customer_car_picture_insert');
        }

        $this->assertSame([], Storage::disk('customer_car_media_local')->allFiles('customer-cars'));
        $this->assertDatabaseCount('customer_cars', 0);
        $this->assertDatabaseCount('customer_car_pictures', 0);
    }

    public function test_failed_individual_file_deletion_preserves_hidden_metadata_and_is_retried(): void
    {
        $customer = User::factory()->customer()->create();
        $car = $this->createCar($customer, ['pictures' => [$this->image()]]);
        $picture = $car->pictures()->firstOrFail();
        $filesystem = Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('exists')->twice()->with($picture->path)->andReturnTrue();
        $filesystem->shouldReceive('delete')->twice()->with($picture->path)->andReturnFalse();
        $factory = Mockery::mock(FilesystemFactory::class);
        $factory->shouldReceive('disk')->twice()->with($picture->disk)->andReturn($filesystem);
        $this->app->instance(FilesystemFactory::class, $factory);

        $this->actingAs($customer, 'sanctum')
            ->deleteJson('/api/customer/customer-cars/'.$car->id.'/pictures/'.$picture->id)
            ->assertOk();

        $this->assertSoftDeleted('customer_car_pictures', ['id' => $picture->id]);
        Storage::disk('customer_car_media_local')->assertExists($picture->path);
        $this->assertSame(0, $car->pictures()->count());
        $this->assertDatabaseHas('pending_image_deletions', ['disk' => $picture->disk, 'path' => $picture->path]);

        $this->app->instance(FilesystemFactory::class, Storage::getFacadeRoot());
        $this->artisan('images:cleanup')->assertSuccessful();

        Storage::disk('customer_car_media_local')->assertMissing($picture->path);
        $this->assertDatabaseCount('pending_image_deletions', 0);
    }

    public function test_individual_deletion_rolls_back_if_its_cleanup_job_cannot_be_persisted(): void
    {
        $customer = User::factory()->customer()->create();
        $car = $this->createCar($customer, ['pictures' => [$this->image()]]);
        $picture = $car->pictures()->firstOrFail();
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER fail_image_cleanup_insert
            BEFORE INSERT ON pending_image_deletions
            BEGIN
                SELECT RAISE(ABORT, 'Simulated cleanup persistence failure');
            END;
            SQL);

        try {
            $this->actingAs($customer, 'sanctum')
                ->deleteJson('/api/customer/customer-cars/'.$car->id.'/pictures/'.$picture->id)
                ->assertStatus(500);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_image_cleanup_insert');
        }

        $this->assertNotSoftDeleted('customer_car_pictures', ['id' => $picture->id]);
        $this->assertDatabaseCount('pending_image_deletions', 0);
        Storage::disk('customer_car_media_local')->assertExists($picture->path);
    }

    public function test_direct_picture_force_delete_removes_its_private_file(): void
    {
        $customer = User::factory()->customer()->create();
        $car = $this->createCar($customer, ['pictures' => [$this->image()]]);
        $picture = $car->pictures()->firstOrFail();

        $picture->forceDelete();

        Storage::disk('customer_car_media_local')->assertMissing($picture->path);
        $this->assertDatabaseMissing('customer_car_pictures', ['id' => $picture->id]);
    }

    public function test_direct_picture_force_delete_retains_metadata_when_storage_is_unavailable(): void
    {
        $customer = User::factory()->customer()->create();
        $car = $this->createCar($customer, ['pictures' => [$this->image()]]);
        $picture = $car->pictures()->firstOrFail();
        $filesystem = Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('exists')->once()->with($picture->path)->andReturnTrue();
        $filesystem->shouldReceive('delete')->once()->with($picture->path)->andReturnFalse();
        $factory = Mockery::mock(FilesystemFactory::class);
        $factory->shouldReceive('disk')->once()->with($picture->disk)->andReturn($filesystem);
        $this->app->instance(FilesystemFactory::class, $factory);

        try {
            $picture->forceDelete();
            $this->fail('Expected failed file cleanup to prevent metadata deletion.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame(503, $exception->statusCode());
        }

        $this->assertDatabaseHas('customer_car_pictures', ['id' => $picture->id]);
        Storage::disk('customer_car_media_local')->assertExists($picture->path);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function postCar(User $customer, array $overrides = [])
    {
        return $this->postCarWithKey(
            $customer,
            (string) Str::uuid(),
            $this->payload($overrides),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postCarWithKey(User $customer, string $key, array $payload)
    {
        return $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', $key)
            ->post('/api/customer/customer-cars', $payload);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCar(
        User $customer,
        array $overrides = [],
        ?string $idempotencyKey = null,
    ): CustomerCar {
        $response = $this->postCarWithKey(
            $customer,
            $idempotencyKey ?? (string) Str::uuid(),
            $this->payload($overrides),
        )->assertCreated();

        return CustomerCar::query()->findOrFail($response->json('data.id'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'car_name_id' => CarName::factory()->create()->id,
            'manufacturing_year' => 2022,
            'color_id' => Color::factory()->create()->id,
            'fuel_type' => FuelType::factory()->create()->id,
            ...$overrides,
        ];
    }

    private function image(string $contentSuffix = ''): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'car.png',
            base64_decode(self::PNG).$contentSuffix,
        );
    }
}
