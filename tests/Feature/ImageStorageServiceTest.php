<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\StoreCarPicture;
use App\Services\ImageStorageService;
use App\Support\ImageRules;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ImageStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAF/gL+9/3K8QAAAABJRU5ErkJggg==';

    public function test_uploads_use_private_generated_paths_and_copies_have_independent_lifetimes(): void
    {
        config(['images.disk' => 'images_local']);
        Storage::fake('images_local');
        $service = $this->app->make(ImageStorageService::class);
        $storedFiles = [];

        $original = $service->store($this->image(), 'registrations/4', $storedFiles);
        $copy = $service->copy($original, 'stores/8', $storedFiles);

        $this->assertSame('images_local', $original['disk']);
        $this->assertSame('image/png', $original['mime_type']);
        $this->assertSame(strlen(base64_decode(self::PNG)), $original['size_bytes']);
        $this->assertStringStartsWith('registrations/4/', $original['path']);
        $this->assertStringNotContainsString('client-name', $original['path']);
        $this->assertNotSame($original['path'], $copy['path']);
        // Windows does not implement Unix file mode visibility. The disk is
        // outside the public root and every write explicitly requests private.
        $this->assertSame('private', config('filesystems.disks.images_local.visibility'));

        $service->delete($original);

        Storage::disk('images_local')->assertMissing($original['path']);
        Storage::disk('images_local')->assertExists($copy['path']);
        $this->assertSame(base64_decode(self::PNG), Storage::disk('images_local')->get($copy['path']));

        $service->cleanup($storedFiles);
        $this->assertSame([], Storage::disk('images_local')->allFiles());
    }

    public function test_recorded_disk_keeps_existing_images_readable_after_the_default_changes(): void
    {
        Storage::fake('old_images');
        Storage::fake('images_local');
        $service = $this->app->make(ImageStorageService::class);
        $storedFiles = [];
        $image = $service->store($this->image(), 'cars/1', $storedFiles, 'old_images');
        config(['images.disk' => 'images_local']);

        $response = $service->stream($image);

        $this->assertNotNull($response);
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $service->delete($image);
        Storage::disk('old_images')->assertMissing($image['path']);
    }

    public function test_legacy_values_do_not_resolve_a_disk_or_delete_a_client_supplied_path(): void
    {
        $factory = Mockery::mock(FilesystemFactory::class);
        $factory->shouldNotReceive('disk');
        $service = new ImageStorageService($factory);

        foreach ([[], ['disk' => null, 'path' => '/etc/passwd'], ['disk' => 'local', 'path' => null]] as $image) {
            $service->delete($image);
            $this->assertNull($service->stream($image));
        }
    }

    public function test_direct_store_car_picture_force_delete_retains_metadata_until_file_deletion_succeeds(): void
    {
        config(['images.disk' => 'images_local']);
        Storage::fake('images_local');
        $storedFiles = [];
        $metadata = $this->app->make(ImageStorageService::class)->store($this->image(), 'store-cars/1', $storedFiles);
        $picture = StoreCarPicture::factory()->create($metadata);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->with($picture->path)->andReturnTrue();
        $disk->shouldReceive('delete')->once()->with($picture->path)->andReturnFalse();
        $factory = Mockery::mock(FilesystemFactory::class);
        $factory->shouldReceive('disk')->once()->with($picture->disk)->andReturn($disk);
        $this->app->instance(FilesystemFactory::class, $factory);

        try {
            $picture->forceDelete();
            $this->fail('Expected failed image deletion to retain picture metadata.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame(503, $exception->statusCode());
        }

        $this->assertDatabaseHas('store_car_pictures', ['id' => $picture->id]);
        Storage::disk('images_local')->assertExists($picture->path);

        $this->app->instance(FilesystemFactory::class, Storage::getFacadeRoot());
        $picture->forceDelete();

        $this->assertDatabaseMissing('store_car_pictures', ['id' => $picture->id]);
        Storage::disk('images_local')->assertMissing($picture->path);
    }

    public function test_failed_write_registers_its_path_for_rollback_cleanup(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->withArgs(
            fn ($directory, $file, $filename, $options) => $directory === 'parts/1'
                && $file instanceof UploadedFile
                && str_ends_with($filename, '.png')
                && $options === ['visibility' => 'private'],
        )->andReturnFalse();
        $factory = Mockery::mock(FilesystemFactory::class);
        $factory->shouldReceive('disk')->with('images_local')->once()->andReturn($disk);
        config(['images.disk' => 'images_local']);
        $service = new ImageStorageService($factory);
        $storedFiles = [];

        try {
            $service->store($this->image(), 'parts/1', $storedFiles);
            $this->fail('Expected a failed write to abort persistence.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unable to store image.', $exception->getMessage());
        }

        $this->assertCount(1, $storedFiles);
        $this->assertSame('images_local', $storedFiles[0]['disk']);
        $this->assertStringStartsWith('parts/1/', $storedFiles[0]['path']);
    }

    public function test_cleanup_reports_false_deletes_and_continues_with_remaining_files(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->with('first.png')->andReturnTrue();
        $disk->shouldReceive('delete')->once()->with('first.png')->andReturnFalse();
        $disk->shouldReceive('exists')->once()->with('second.png')->andReturnTrue();
        $disk->shouldReceive('delete')->once()->with('second.png')->andReturnTrue();
        $factory = Mockery::mock(FilesystemFactory::class);
        $factory->shouldReceive('disk')->with('images_local')->twice()->andReturn($disk);
        $handler = Mockery::mock(ExceptionHandler::class);
        $handler->shouldReceive('report')->once()->with(Mockery::on(
            fn ($exception) => $exception instanceof BusinessRuleException && $exception->statusCode() === 503,
        ));
        $this->app->instance(ExceptionHandler::class, $handler);

        (new ImageStorageService($factory))->cleanup([
            ['disk' => 'images_local', 'path' => 'first.png'],
            ['disk' => 'images_local', 'path' => 'second.png'],
        ]);

        $this->assertDatabaseHas('pending_image_deletions', ['disk' => 'images_local', 'path' => 'first.png']);
        $this->assertDatabaseMissing('pending_image_deletions', ['disk' => 'images_local', 'path' => 'second.png']);
    }

    public function test_failed_replacement_cleanup_keeps_a_durable_reference_until_the_command_retries(): void
    {
        config(['images.disk' => 'images_local']);
        Storage::fake('images_local');
        $images = $this->app->make(ImageStorageService::class);
        $storedFiles = [];
        $oldImage = $images->store($this->image(), 'stores/1/registration', $storedFiles);

        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->with($oldImage['path'])->andReturnTrue();
        $disk->shouldReceive('delete')->once()->with($oldImage['path'])->andReturnFalse();
        $factory = Mockery::mock(FilesystemFactory::class);
        $factory->shouldReceive('disk')->once()->with('images_local')->andReturn($disk);
        $unavailableImages = new ImageStorageService($factory);

        DB::transaction(function () use ($oldImage, $unavailableImages): void {
            $unavailableImages->deleteAfterCommit([$oldImage]);
            Storage::disk('images_local')->assertExists($oldImage['path']);
        });

        $this->assertDatabaseHas('pending_image_deletions', ['disk' => 'images_local', 'path' => $oldImage['path']]);
        Storage::disk('images_local')->assertExists($oldImage['path']);

        $this->artisan('images:cleanup')->assertSuccessful();

        $this->assertDatabaseCount('pending_image_deletions', 0);
        Storage::disk('images_local')->assertMissing($oldImage['path']);
    }

    public function test_a_rolled_back_replacement_does_not_delete_or_schedule_the_original_image(): void
    {
        config(['images.disk' => 'images_local']);
        Storage::fake('images_local');
        $images = $this->app->make(ImageStorageService::class);
        $storedFiles = [];
        $oldImage = $images->store($this->image(), 'stores/1/registration', $storedFiles);

        try {
            DB::transaction(function () use ($oldImage, $images): void {
                $images->deleteAfterCommit([$oldImage]);
                throw new RuntimeException('Simulated replacement failure.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated replacement failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('pending_image_deletions', 0);
        Storage::disk('images_local')->assertExists($oldImage['path']);
        $this->assertSame(0, $images->retryPendingDeletions());
    }

    public function test_shared_validation_rejects_strings_svg_and_images_outside_configured_dimensions(): void
    {
        $this->assertTrue(Validator::make(['picture' => '/client/path.png'], ['picture' => ImageRules::file()])->fails());
        $this->assertTrue(Validator::make([
            'picture' => UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
        ], ['picture' => ImageRules::file()])->fails());
        $this->assertFalse(Validator::make(['picture' => $this->image()], ['picture' => ImageRules::file()])->fails());

        config(['images.min_width' => 2]);
        $this->assertTrue(Validator::make(['picture' => $this->image()], ['picture' => ImageRules::file()])->fails());
        $this->assertFalse(Validator::make([
            'picture' => $this->image(),
        ], ['picture' => ImageRules::file(['min_width' => 1])])->fails());
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('client-name.png', base64_decode(self::PNG));
    }
}
