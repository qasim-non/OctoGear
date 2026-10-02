<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Events\OrderCreated;
use App\Models\Admin;
use App\Models\CarName;
use App\Models\Color;
use App\Models\FuelType;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\User;
use App\Services\ImageStorageService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class OrderImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['images.disk' => 'images_local']);
        Storage::fake('images_local');
        Event::fake([OrderCreated::class]);
        $this->actingAs(User::factory()->customer()->create(), 'sanctum')
            ->withHeader('Idempotency-Key', (string) Str::uuid());
    }

    public function test_multiple_uploads_are_private_ordered_and_exposed_in_customer_provider_and_admin_reads(): void
    {
        $response = $this->post('/api/customer/orders', [...$this->payload(), 'images' => [$this->image('one'), $this->image('two')]])
            ->assertCreated()->assertJsonCount(2, 'data.images')->assertJsonMissingPath('data.customer_image');
        $order = Order::findOrFail($response->json('data.id'));
        $this->assertSame([0, 1], $order->images->pluck('sort_order')->all());
        foreach ($order->images as $index => $file) {
            Storage::disk('images_local')->assertExists($file->path);
            $this->assertArrayNotHasKey('path', $file->toArray());
            $this->assertArrayNotHasKey('disk', $file->toArray());
            $response->assertJsonMissingPath('data.images.'.$index.'.path')->assertJsonMissingPath('data.images.'.$index.'.disk');
            $this->get($response->json('data.images.'.$index.'.url'))->assertOk()
                ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        }
        $this->getJson('/api/customer/orders/'.$order->id)->assertOk()->assertJsonCount(2, 'data.images');
        $this->getJson('/api/customer/orders')->assertOk()->assertJsonCount(2, 'data.0.images');
        $provider = Store::factory()->create(['city_id' => $order->customer->city_id])->owner;
        $this->actingAs($provider, 'sanctum')->getJson('/api/provider/orders/'.$order->id)->assertOk()->assertJsonCount(2, 'data.images');
        $this->getJson('/api/provider/orders/general')->assertOk()->assertJsonCount(2, 'data.0.images');
        $this->actingAs(Admin::factory()->create(), 'sanctum')->getJson('/api/admin/orders/'.$order->id)->assertOk()->assertJsonCount(2, 'data.images');
        $this->getJson('/api/admin/orders')->assertOk()->assertJsonCount(2, 'data.0.images');
        Event::assertDispatchedTimes(OrderCreated::class, 1);
    }

    public function test_images_are_optional_and_the_scalar_image_columns_are_gone(): void
    {
        $payload = $this->payload();
        foreach ([$payload, [...$payload, 'images' => []]] as $input) {
            $this->postJson('/api/customer/orders', $input)->assertCreated()->assertJsonPath('data.images', []);
        }
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_images', 0);
        foreach (['customer_image', 'customer_image_disk', 'customer_image_path', 'customer_image_size_bytes', 'customer_image_mime_type'] as $column) {
            $this->assertFalse(Schema::hasColumn('orders', $column));
        }
    }

    public function test_specific_request_gallery_is_private_and_remains_in_the_provider_rejection_response(): void
    {
        $part = StoreCarComponent::factory()->create(['stock_quantity' => 2]);
        $response = $this->post('/api/customer/orders', [
            'order_type' => 'specific', 'store_car_component_id' => $part->id,
            'quantity' => 1, 'images' => [$this->image(), $this->image('two')],
        ])->assertCreated()->assertJsonCount(2, 'data.images');
        $url = $response->json('data.images.0.url');
        $this->actingAs(User::factory()->provider()->create(), 'sanctum')->getJson($url)->assertForbidden();
        $this->actingAs($part->storeCar->store->owner, 'sanctum')->get($url)->assertOk();
        $this->getJson('/api/provider/orders/specific')->assertOk()->assertJsonCount(2, 'data.0.images');
        $this->postJson('/api/provider/orders/'.$response->json('data.id').'/reject')
            ->assertOk()->assertJsonPath('data.images', $response->json('data.images'));
    }

    public function test_identical_retries_do_not_duplicate_images_and_changed_order_or_content_conflicts(): void
    {
        $payload = $this->payload();
        $first = $this->post('/api/customer/orders', [...$payload, 'images' => [$this->image('one'), $this->image('two')]])->assertCreated();
        $this->post('/api/customer/orders', [...$payload, 'images' => [$this->image('one'), $this->image('two')]])
            ->assertCreated()->assertJsonPath('data.id', $first->json('data.id'))->assertJsonPath('data.images', $first->json('data.images'));
        foreach ([[$this->image('two'), $this->image('one')], [$this->image('changed'), $this->image('two')], [$this->image('one')], []] as $files) {
            $this->post('/api/customer/orders', [...$payload, 'images' => $files])->assertConflict();
        }
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_images', 2);
        $this->assertCount(2, Storage::disk('images_local')->allFiles());
        Event::assertDispatchedTimes(OrderCreated::class, 1);
    }

    public function test_invalid_files_limits_and_untrusted_paths_are_rejected_in_both_languages(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $payload = $this->payload();
        foreach (['en', 'ar'] as $language) {
            $this->withHeader('Accept-Language', $language);
            foreach (['images/fake.png', ['https://example.test/fake.png'], [$this->image(), UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')], [$this->image()->size(5121)], array_fill(0, 6, $this->image()), ['path' => $this->image()]] as $images) {
                $response = $this->post('/api/customer/orders', [...$payload, 'images' => $images])->assertUnprocessable();
                $this->assertStringNotContainsString('auth.validation.', $response->getContent());
            }
            $this->post('/api/customer/orders', [...$payload, 'customer_image' => $this->image()])->assertUnprocessable()->assertJsonValidationErrors('customer_image');
        }
        config(['images.min_width' => 2]);
        $this->post('/api/customer/orders', [...$payload, 'images' => [$this->image()]])->assertUnprocessable()->assertJsonValidationErrors('images.0');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame([], Storage::disk('images_local')->allFiles());
    }

    public function test_the_configured_image_count_limit_is_enforced(): void
    {
        config(['images.max_files' => 2]);
        $payload = $this->payload();
        $this->post('/api/customer/orders', [...$payload, 'images' => [$this->image(), $this->image(), $this->image()]])
            ->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->post('/api/customer/orders', [...$payload, 'images' => [$this->image(), $this->image()]])->assertCreated()->assertJsonCount(2, 'data.images');
    }

    public function test_image_access_checks_the_order_owner_provider_status_admin_and_nested_parent(): void
    {
        $response = $this->post('/api/customer/orders', [...$this->payload(), 'images' => [$this->image()]])->assertCreated();
        $order = Order::findOrFail($response->json('data.id'));
        $url = $response->json('data.images.0.url');
        $imageId = $response->json('data.images.0.id');
        $other = Order::factory()->create(['customer_id' => $order->customer_id]);
        $this->getJson('/api/media/orders/'.$other->id.'/images/'.$imageId)->assertNotFound();
        $this->actingAs(User::factory()->customer()->create(), 'sanctum')->getJson($url)->assertForbidden();
        $provider = User::factory()->provider()->create();
        $this->actingAs($provider, 'sanctum')->get($url)->assertOk();
        $winner = Store::factory()->create();
        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id, 'store_id' => $winner->id, 'status' => OfferStatus::Accepted,
        ]);
        $order->update(['status' => OrderStatus::AwaitingPayment, 'accepted_offer_id' => $offer->id]);
        $this->getJson($url)->assertForbidden();
        $this->actingAs($winner->owner, 'sanctum')->get($url)->assertOk();
        $this->actingAs(Admin::factory()->create(), 'sanctum')->get($url)->assertOk();
        $this->actingAs(Admin::factory()->blocked()->create(), 'sanctum')->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->blocked()->customer()->create(), 'sanctum')->getJson($url)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson($url)->assertUnauthorized();
    }

    public function test_failure_on_the_second_image_rolls_back_all_files_order_snapshot_garage_car_and_event(): void
    {
        $payload = [...$this->payload(), 'save_to_my_cars' => true, 'images' => [$this->image('one'), $this->image('two')]];
        DB::unprepared("CREATE TRIGGER fail_second_image BEFORE INSERT ON order_images WHEN NEW.sort_order = 1 BEGIN SELECT RAISE(ABORT, 'Simulated failure'); END;");
        try {
            $this->post('/api/customer/orders', $payload)->assertStatus(500);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_second_image');
        }
        foreach (['orders', 'order_images', 'order_vehicle_details', 'customer_cars'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame([], Storage::disk('images_local')->allFiles());
        Event::assertNotDispatched(OrderCreated::class);
        $this->post('/api/customer/orders', $payload)->assertCreated()->assertJsonCount(2, 'data.images');
    }

    public function test_soft_deletion_hides_images_and_force_deletion_removes_their_files(): void
    {
        $response = $this->post('/api/customer/orders', [...$this->payload(), 'images' => [$this->image(), $this->image('two')]])->assertCreated();
        $order = Order::findOrFail($response->json('data.id'));
        $files = $order->images->pluck('path')->all();
        $order->delete();
        foreach ($files as $path) {
            Storage::disk('images_local')->assertExists($path);
        }
        $this->getJson($response->json('data.images.0.url'))->assertNotFound();
        $order->forceDelete();
        $this->assertDatabaseCount('order_images', 0);
        foreach ($files as $path) {
            Storage::disk('images_local')->assertMissing($path);
        }
    }

    public function test_failed_cleanup_is_queued_and_retried_after_order_deletion(): void
    {
        $id = $this->post('/api/customer/orders', [...$this->payload(), 'images' => [$this->image()]])->assertCreated()->json('data.id');
        $order = Order::findOrFail($id);
        $path = $order->images->sole()->path;
        $this->partialMock(ImageStorageService::class, fn ($mock) => $mock->shouldReceive('delete')->andThrow(new RuntimeException('Storage unavailable')));
        $order->forceDelete();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_images', 0);
        $this->assertDatabaseCount('pending_image_deletions', 1);
        Storage::disk('images_local')->assertExists($path);
        $this->app->forgetInstance(ImageStorageService::class);
        $this->artisan('images:cleanup')->assertSuccessful();
        Storage::disk('images_local')->assertMissing($path);
        $this->assertDatabaseCount('pending_image_deletions', 0);
    }

    public function test_failed_cleanup_job_write_rolls_back_order_deletion_and_preserves_files(): void
    {
        $id = $this->post('/api/customer/orders', [...$this->payload(), 'images' => [$this->image()]])->assertCreated()->json('data.id');
        $order = Order::findOrFail($id);
        $path = $order->images->sole()->path;
        DB::unprepared("CREATE TRIGGER fail_cleanup_job BEFORE INSERT ON pending_image_deletions BEGIN SELECT RAISE(ABORT, 'Simulated failure'); END;");
        try {
            try {
                $order->forceDelete();
                $this->fail('Deletion must roll back when the file cleanup reference cannot be retained.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('Simulated failure', $exception->getMessage());
            }
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_cleanup_job');
        }
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_images', 1);
        Storage::disk('images_local')->assertExists($path);
    }

    private function payload(): array
    {
        return ['order_type' => 'general', 'component_name' => 'Both mirrors', 'vehicle' => [
            'car_name_id' => CarName::factory()->create()->id, 'manufacturing_year' => 2020,
            'transmission_type' => 'manual', 'color_id' => Color::factory()->create()->id,
            'fuel_type' => FuelType::factory()->create()->id,
        ]];
    }

    private function image(string $suffix = ''): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('mirror.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAF/gL+9/3K8QAAAABJRU5ErkJggg==').$suffix);
    }
}
