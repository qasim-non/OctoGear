<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Events\OrderCreated;
use App\Models\Order;
use App\Models\StoreCarComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SpecificPartRequestTest extends TestCase
{
    use RefreshDatabase;

    private StoreCarComponent $part;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([OrderCreated::class]);
        config(['images.disk' => 'images_local']);
        Storage::fake('images_local');
        $this->part = StoreCarComponent::factory()->create(['stock_quantity' => 3]);
    }

    public function test_specific_request_targets_its_store_and_does_not_charge_or_reserve_stock(): void
    {
        $customer = User::factory()->customer()->create();
        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/customer/orders', [
            ...$this->payload(), 'offered_price' => 1, 'accepted_store_id' => 999,
            'customer_id' => 999, 'status' => 'paid',
        ])->assertCreated()->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.quantity', 2)->assertJsonPath('data.offered_price', null);
        $order = Order::findOrFail($response->json('data.id'));
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame($this->part->storeCar->store_id, $order->store->id);
        $this->assertNull($order->accepted_store_id);
        $this->assertSame(3, $this->part->fresh()->stock_quantity);
        $this->assertDatabaseCount('payments', 0);
        Event::assertDispatchedTimes(OrderCreated::class, 1);
    }

    public function test_retry_returns_original_even_after_stock_and_store_change_without_duplicate_photo_or_event(): void
    {
        $this->actingAs(User::factory()->customer()->create(), 'sanctum');
        $headers = ['Idempotency-Key' => (string) Str::uuid(), 'Accept' => 'application/json'];
        $first = $this->post('/api/customer/orders', [...$this->payload(), 'images' => [$this->photo()]], $headers)->assertCreated();
        $this->part->update(['stock_quantity' => 0]);
        $this->part->storeCar->store->update(['status' => StoreStatus::Inactive]);
        $this->post('/api/customer/orders', [...$this->payload(), 'images' => [$this->photo()]], $headers)
            ->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertCount(1, Storage::disk('images_local')->allFiles());
        $this->get($first->json('data.images.0.url'))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertArrayNotHasKey('idempotency_key', Order::first()->toArray());
        Event::assertDispatchedTimes(OrderCreated::class, 1);
    }

    public function test_same_key_with_changed_quantity_or_deleted_request_conflicts(): void
    {
        $this->actingAs(User::factory()->customer()->create(), 'sanctum');
        $headers = ['Idempotency-Key' => (string) Str::uuid()];
        $this->postJson('/api/customer/orders', $this->payload(), $headers)->assertCreated();
        $this->postJson('/api/customer/orders', [...$this->payload(), 'quantity' => 1], $headers)->assertConflict();
        $this->postJson('/api/customer/orders', [...$this->payload(), 'notes' => 'Different'], $headers)->assertConflict();
        Order::first()->delete();
        $this->postJson('/api/customer/orders', $this->payload(), $headers)->assertConflict();
        $this->assertSame(1, Order::withTrashed()->count());
        Event::assertDispatchedTimes(OrderCreated::class, 1);
    }

    public function test_request_keys_are_scoped_to_customer_and_validated(): void
    {
        $headers = ['Idempotency-Key' => (string) Str::uuid()];
        foreach (range(1, 2) as $_) {
            $this->actingAs(User::factory()->customer()->create(), 'sanctum')
                ->postJson('/api/customer/orders', $this->payload(), $headers)->assertCreated();
        }
        $this->postJson('/api/customer/orders', $this->payload(), ['Idempotency-Key' => 'bad'])
            ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_current_stock_and_active_store_are_required_before_creation(): void
    {
        $this->actingAs(User::factory()->customer()->create(), 'sanctum');
        $this->postJson('/api/customer/orders', [...$this->payload(), 'quantity' => 4])
            ->assertUnprocessable()->assertJsonPath('success', false)->assertJsonValidationErrors('store_car_component_id');
        $this->part->update(['stock_quantity' => 0]);
        $this->postJson('/api/customer/orders', $this->payload())->assertUnprocessable();
        $this->part->update(['stock_quantity' => 3]);
        $this->part->storeCar->store->update(['status' => StoreStatus::Inactive]);
        $this->postJson('/api/customer/orders', $this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        Event::assertNotDispatched(OrderCreated::class);
    }

    public function test_deleted_inventory_car_and_reference_cannot_receive_requests(): void
    {
        $this->actingAs(User::factory()->customer()->create(), 'sanctum');
        $this->part->delete();
        $this->postJson('/api/customer/orders', $this->payload())->assertUnprocessable();
        $this->part->restore();
        $this->part->storeCar->delete();
        $this->postJson('/api/customer/orders', $this->payload())->assertUnprocessable();
        $this->part->storeCar->restore();
        $this->part->component->delete();
        $this->postJson('/api/customer/orders', $this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_quantity_notes_photo_and_customer_role_are_validated(): void
    {
        $this->postJson('/api/customer/orders', $this->payload())->assertUnauthorized();
        $this->actingAs(User::factory()->provider()->create(), 'sanctum')
            ->postJson('/api/customer/orders', $this->payload())->assertForbidden();
        $this->actingAs(User::factory()->customer()->create(), 'sanctum');
        foreach ([0, -1, 1.5, 2147483648] as $quantity) {
            $this->postJson('/api/customer/orders', [...$this->payload(), 'quantity' => $quantity])
                ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        }
        $this->postJson('/api/customer/orders', [...$this->payload(), 'notes' => str_repeat('a', 1001)])
            ->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->post('/api/customer/orders', [...$this->payload(), 'images' => [UploadedFile::fake()->create('secret.txt', 1)]])
            ->assertUnprocessable()->assertJsonValidationErrors('images.0');
        $this->assertDatabaseCount('orders', 0);
    }

    private function payload(): array
    {
        return ['order_type' => 'specific', 'store_car_component_id' => $this->part->id, 'quantity' => 2, 'notes' => 'Please check the connector.'];
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('private-name.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAF/gL+9/3K8QAAAABJRU5ErkJggg=='));
    }
}
