<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\StoreStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\StoresCar;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProviderManagedOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function direct(User $provider, array $attributes = []): Order
    {
        $store = Store::factory()->create(['user_id' => $provider->id, 'status' => StoreStatus::Active]);
        $car = StoresCar::factory()->create(['store_id' => $store->id]);
        $part = StoreCarComponent::factory()->create(['store_car_id' => $car->id, 'price' => 9000, 'stock_quantity' => 5]);

        return Order::factory()->create([
            'order_type' => OrderType::Specific, 'status' => OrderStatus::Pending,
            'customer_id' => User::factory()->customer()->create()->id,
            'store_car_component_id' => $part->id, 'requested_unit_price' => 12345,
            'offered_price' => null, 'quantity' => 2, ...$attributes,
        ]);
    }

    public function test_direct_lists_are_owned_validated_localized_and_paginated(): void
    {
        $provider = User::factory()->provider()->create();
        $order = $this->direct($provider);
        $part = $order->storeCarComponent;
        $part->component->update(['name_en' => 'Headlight', 'name_ar' => 'مصباح أمامي']);
        $this->direct(User::factory()->provider()->create());
        $this->direct($provider, ['status' => OrderStatus::Rejected]);
        $this->actingAs($provider, 'sanctum')->getJson('/api/provider/orders/specific?status=pending', ['Accept-Language' => 'en'])
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.part_name', 'Headlight')->assertJsonPath('data.0.total_amount', 24690)
            ->assertJsonPath('data.0.can_accept', true)->assertJsonPath('data.0.sold_to', null)
            ->assertJsonMissingPath('data.0.customer.mobile');
        $this->getJson('/api/provider/orders/specific?status=pending', ['Accept-Language' => 'ar'])
            ->assertOk()->assertJsonPath('data.0.part_name', 'مصباح أمامي');
        $this->getJson('/api/provider/orders/specific?page=0')->assertUnprocessable();
        $this->getJson('/api/provider/orders/specific?status=bad')->assertUnprocessable();
        $this->getJson('/api/provider/orders/paid?status=pending')->assertUnprocessable();
        Order::factory()->count(16)->create([
            'order_type' => OrderType::Specific, 'status' => OrderStatus::Pending,
            'store_car_component_id' => $part->id, 'created_at' => '2026-10-01 10:00:00',
            'customer_id' => $order->customer_id,
        ]);
        $first = $this->getJson('/api/provider/orders/specific?status=pending')->assertOk()->json('data');
        $second = $this->getJson('/api/provider/orders/specific?status=pending&page=2')->assertOk()->json('data');
        $this->assertCount(17, array_unique(array_column([...$first, ...$second], 'id')));
    }

    public function test_accept_uses_snapshot_price_is_retry_safe_and_does_not_consume_stock(): void
    {
        $provider = User::factory()->provider()->create();
        $order = $this->direct($provider);
        $this->actingAs($provider, 'sanctum');
        $body = ['expected_unit_price' => 12345, 'expected_quantity' => 2];
        for ($i = 0; $i < 2; $i++) {
            $this->postJson("/api/provider/orders/{$order->id}/accept", $body)
                ->assertOk()->assertJsonPath('data.status', 'awaiting_payment')
                ->assertJsonPath('data.unit_price', 12345)->assertJsonPath('data.total_amount', 24690)
                ->assertJsonPath('data.can_accept', false)->assertJsonPath('data.can_reject', false);
        }
        $this->assertSame(5, $order->storeCarComponent->fresh()->stock_quantity);
        $this->assertSame(0, Payment::count());
        $this->postJson("/api/provider/orders/{$order->id}/reject")->assertBadRequest();
    }

    public function test_stale_price_quantity_stock_and_inactive_stores_cannot_be_accepted(): void
    {
        $provider = User::factory()->provider()->create();
        $order = $this->direct($provider);
        $url = "/api/provider/orders/{$order->id}/accept";
        $this->actingAs($provider, 'sanctum')->postJson($url, [])->assertUnprocessable();
        $this->postJson($url, ['expected_unit_price' => 1, 'expected_quantity' => 2])->assertBadRequest();
        $this->postJson($url, ['expected_unit_price' => 12345, 'expected_quantity' => 1])->assertBadRequest();
        $order->storeCarComponent->update(['stock_quantity' => 1]);
        $body = ['expected_unit_price' => 12345, 'expected_quantity' => 2];
        $this->postJson($url, $body)->assertBadRequest();
        $order->storeCarComponent->update(['stock_quantity' => 5]);
        $order->storeCarComponent->storeCar->store->update(['status' => StoreStatus::Inactive]);
        $this->postJson($url, $body)->assertBadRequest();
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_rejection_rechecks_database_state_and_retries_are_idempotent(): void
    {
        $provider = User::factory()->provider()->create();
        $order = $this->direct($provider);
        $this->actingAs($provider, 'sanctum');
        $url = "/api/provider/orders/{$order->id}/reject";
        $this->postJson($url)->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->postJson($url)->assertOk()->assertJsonPath('data.status', 'rejected');
        $order->update(['status' => OrderStatus::Pending]);
        $stale = $order->fresh();
        $order->update(['status' => OrderStatus::Paid]);
        try {
            app(OrderService::class)->reject($stale);
            $this->fail('Stale rejection must not overwrite paid status.');
        } catch (BusinessRuleException) {
            $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        }
    }

    public function test_paid_history_uses_recorded_payment_and_preserves_existing_inventory_visibility(): void
    {
        $provider = User::factory()->provider()->create();
        $order = $this->direct($provider, ['status' => OrderStatus::Completed, 'offered_price' => 500]);
        Payment::factory()->create(['order_id' => $order->id, 'amount' => 24690, 'payment_status' => PaymentStatus::Paid]);
        $this->actingAs($provider, 'sanctum')->getJson('/api/provider/orders/paid')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/provider/orders/paid?status=completed')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.gross_amount', 24690)->assertJsonPath('data.0.net_amount', 23455)
            ->assertJsonPath('data.0.commission_is_estimate', true);
        $this->getJson("/api/provider/orders/paid/{$order->id}")->assertOk()->assertJsonPath('data.can_accept', false)
            ->assertJsonPath('data.can_reject', false)->assertJsonMissingPath('data.sold_to.mobile');
        $order->storeCarComponent->delete();
        $this->getJson('/api/provider/orders/paid?status=completed')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson("/api/provider/orders/paid/{$order->id}")->assertNotFound();
        $this->getJson("/api/provider/orders/{$order->id}")->assertForbidden();
    }

    public function test_other_providers_and_customers_cannot_access_or_mutate_orders(): void
    {
        $order = $this->direct(User::factory()->provider()->create());
        $this->actingAs(User::factory()->provider()->create(), 'sanctum');
        $this->getJson("/api/provider/orders/{$order->id}")->assertForbidden();
        $this->postJson("/api/provider/orders/{$order->id}/accept", ['expected_unit_price' => 12345, 'expected_quantity' => 2])->assertForbidden();
        $this->postJson("/api/provider/orders/{$order->id}/reject")->assertForbidden();
        $this->getJson("/api/provider/orders/paid/{$order->id}")->assertNotFound();
        $this->actingAs($order->customer, 'sanctum')->getJson('/api/provider/orders/specific')->assertForbidden();
    }

    public function test_direct_order_can_progress_from_acceptance_to_payment_and_customer_receipt(): void
    {
        Event::fake();
        config(['payments.driver' => 'stub']);
        $provider = User::factory()->provider()->create();
        $order = $this->direct($provider);
        $this->actingAs($provider, 'sanctum')->postJson("/api/provider/orders/{$order->id}/accept", ['expected_unit_price' => 12345, 'expected_quantity' => 2])->assertOk();
        $this->actingAs($order->customer, 'sanctum')->postJson("/api/customer/orders/{$order->id}/pay", ['payment_method' => 'credit_card', 'card_token' => 'test-token'])->assertSuccessful();
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(3, $order->storeCarComponent->fresh()->stock_quantity);
        $this->actingAs($provider, 'sanctum')->getJson("/api/provider/orders/paid/{$order->id}")->assertOk()->assertJsonPath('data.total_amount', 24690);
        $this->actingAs($order->customer, 'sanctum')->postJson("/api/customer/orders/{$order->id}/received")->assertSuccessful();
        $this->actingAs($provider, 'sanctum')->getJson('/api/provider/orders/paid?status=completed')->assertOk()->assertJsonPath('data.0.id', $order->id);
    }
}
