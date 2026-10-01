<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Events\OrderCreated;
use App\Models\Admin;
use App\Models\CarName;
use App\Models\Color;
use App\Models\FuelType;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['images.disk' => 'images_local']);
        Storage::fake('images_local');
        Event::fake([OrderCreated::class]);
    }

    public function test_order_upload_stores_private_metadata_and_returns_an_authenticated_url(): void
    {
        $customer = User::factory()->customer()->create();
        $response = $this->actingAs($customer, 'sanctum')->post('/api/customer/orders', $this->payload())
            ->assertCreated();
        $order = Order::findOrFail($response->json('data.id'));

        $this->assertSame('image/png', $order->customer_image_mime_type);
        Storage::disk('images_local')->assertExists($order->customer_image_path);
        $this->assertSame('/api/media/orders/'.$order->id.'/image', $response->json('data.customer_image'));
        $this->assertArrayNotHasKey('customer_image_path', $order->toArray());
        $this->assertArrayNotHasKey('customer_image_disk', $order->toArray());
        $this->get($response->json('data.customer_image'))->assertOk()
            ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        Event::assertDispatched(OrderCreated::class);
    }

    public function test_order_image_uses_order_access_rules_and_blocks_other_customers(): void
    {
        $customer = User::factory()->customer()->create();
        $response = $this->actingAs($customer, 'sanctum')->post('/api/customer/orders', $this->payload())->assertCreated();
        $url = $response->json('data.customer_image');
        $order = Order::findOrFail($response->json('data.id'));
        $provider = User::factory()->provider()->create();
        $winner = Store::factory()->create();

        $this->actingAs(User::factory()->customer()->create(), 'sanctum')->getJson($url)->assertForbidden();
        $this->actingAs($provider, 'sanctum')->get($url)->assertOk();
        $order->update(['status' => OrderStatus::Negotiating, 'accepted_store_id' => $winner->id]);
        $this->getJson($url)->assertForbidden();
        $this->actingAs($winner->owner, 'sanctum')->get($url)->assertOk();
        $this->actingAs(Admin::factory()->create(), 'sanctum')->get($url)->assertOk();
        $this->actingAs(Admin::factory()->blocked()->create(), 'sanctum')->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->blocked()->customer()->create(), 'sanctum')->getJson($url)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson($url)->assertUnauthorized();
    }

    public function test_order_upload_rejects_paths_and_non_images(): void
    {
        $this->actingAs(User::factory()->customer()->create(), 'sanctum');
        foreach (['uploads/client.png', UploadedFile::fake()->create('document.pdf', 1, 'application/pdf')] as $invalid) {
            $this->post('/api/customer/orders', [...$this->payload(), 'customer_image' => $invalid])
                ->assertUnprocessable()->assertJsonValidationErrors('customer_image');
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame([], Storage::disk('images_local')->allFiles());
    }

    public function test_failed_order_image_metadata_write_rolls_back_order_and_file(): void
    {
        DB::unprepared("CREATE TRIGGER fail_order_image BEFORE UPDATE ON orders WHEN NEW.customer_image_path IS NOT NULL BEGIN SELECT RAISE(ABORT, 'Simulated image metadata failure'); END;");
        try {
            $this->actingAs(User::factory()->customer()->create(), 'sanctum')
                ->post('/api/customer/orders', $this->payload())->assertStatus(500);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_order_image');
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame([], Storage::disk('images_local')->allFiles());
        Event::assertNotDispatched(OrderCreated::class);
    }

    public function test_soft_delete_hides_image_and_force_delete_removes_file(): void
    {
        $customer = User::factory()->customer()->create();
        $response = $this->actingAs($customer, 'sanctum')->post('/api/customer/orders', $this->payload())->assertCreated();
        $order = Order::findOrFail($response->json('data.id'));
        $path = $order->customer_image_path;

        $order->delete();
        Storage::disk('images_local')->assertExists($path);
        $this->getJson($response->json('data.customer_image'))->assertNotFound();
        $order->forceDelete();
        Storage::disk('images_local')->assertMissing($path);
    }

    public function test_legacy_paths_are_never_exposed_as_image_urls(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id, 'customer_image_path' => 'https://example.test/untrusted.png']);
        $this->assertNull($order->customerImageUrl());
        $this->actingAs($customer, 'sanctum')->getJson('/api/media/orders/'.$order->id.'/image')->assertNotFound();
    }

    private function payload(): array
    {
        $this->withHeader('Idempotency-Key', (string) Str::uuid());

        return [
            'order_type' => 'general',
            'component_name' => 'Both mirrors',
            'vehicle' => ['car_name_id' => CarName::factory()->create()->id, 'manufacturing_year' => 2020, 'transmission_type' => 'manual', 'color_id' => Color::factory()->create()->id, 'fuel_type' => FuelType::factory()->create()->id],
            'customer_image' => UploadedFile::fake()->createWithContent('image.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAF/gL+9/3K8QAAAABJRU5ErkJggg==')),
        ];
    }
}
