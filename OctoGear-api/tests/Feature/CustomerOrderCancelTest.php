<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderCancelTest extends TestCase
{
    use RefreshDatabase;

    private function authCustomer(): User
    {
        return User::factory()->create(['type' => 'customer']);
    }

    private function makeStore(): Store
    {
        $owner = User::factory()->create(['type' => 'service provider']);

        return Store::factory()->create(['user_id' => $owner->id]);
    }

    private function pendingOrderWithOffers(User $customer): Order
    {
        $store = $this->makeStore();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type'  => OrderType::General,
            'status'      => OrderStatus::Pending,
        ]);

        OrderOffer::factory()->count(2)->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
        ]);

        return $order;
    }

    public function test_cancelling_a_pending_order_deletes_all_its_offers(): void
    {
        $customer = $this->authCustomer();
        $order = $this->pendingOrderWithOffers($customer);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        $this->assertDatabaseMissing('order_offers', [
            'order_id'   => $order->id,
            'deleted_at' => null,
        ]);
    }

    public function test_cancelling_a_negotiating_order_deletes_all_its_offers(): void
    {
        $customer = $this->authCustomer();
        $store = $this->makeStore();

        $order = Order::factory()->create([
            'customer_id'      => $customer->id,
            'order_type'       => OrderType::General,
            'status'           => OrderStatus::Negotiating,
            'offered_price'    => 700,
            'accepted_store_id' => $store->id,
        ]);

        OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/cancel")
            ->assertOk();

        $this->assertDatabaseMissing('order_offers', [
            'order_id'   => $order->id,
            'deleted_at' => null,
        ]);
    }

    public function test_cannot_cancel_a_paid_order(): void
    {
        $customer = $this->authCustomer();
        $store = $this->makeStore();

        $order = Order::factory()->create([
            'customer_id'      => $customer->id,
            'order_type'       => OrderType::Specific,
            'status'           => OrderStatus::Paid,
            'offered_price'    => 450,
            'accepted_store_id' => $store->id,
        ]);

        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/cancel")
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('order_offers', [
            'id'         => $offer->id,
            'deleted_at' => null,
        ]);
    }
}