<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderOfferTest extends TestCase
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

    private function orderWithOffer(User $customer, OrderStatus $status): Order
    {
        $store = $this->makeStore();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type'  => OrderType::General,
            'status'      => $status,
        ]);

        OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
        ]);

        return $order;
    }

    public function test_cannot_view_offers_for_a_paid_order(): void
    {
        $customer = $this->authCustomer();
        $order = $this->orderWithOffer($customer, OrderStatus::Paid);
        $offer = $order->offers()->first();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/customer/orders/{$order->id}/offers")
            ->assertForbidden();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/customer/orders/{$order->id}/offers/{$offer->id}")
            ->assertForbidden();
    }

    public function test_cannot_view_offers_for_a_completed_order(): void
    {
        $customer = $this->authCustomer();
        $order = $this->orderWithOffer($customer, OrderStatus::Completed);
        $offer = $order->offers()->first();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/customer/orders/{$order->id}/offers")
            ->assertForbidden();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/customer/orders/{$order->id}/offers/{$offer->id}")
            ->assertForbidden();
    }

    public function test_cannot_reject_an_offer_on_a_paid_order(): void
    {
        $customer = $this->authCustomer();
        $order = $this->orderWithOffer($customer, OrderStatus::Paid);
        $offer = $order->offers()->first();

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/offers/{$offer->id}/reject", [
                'rejection_reason' => 'Too late',
            ])
            ->assertForbidden();
    }

    public function test_can_view_offers_for_a_negotiating_order(): void
    {
        $customer = $this->authCustomer();
        $order = $this->orderWithOffer($customer, OrderStatus::Negotiating);
        $offer = $order->offers()->first();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/customer/orders/{$order->id}/offers")
            ->assertOk();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/customer/orders/{$order->id}/offers/{$offer->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $offer->id);
    }

    public function test_customer_can_reject_offer(): void
    {
        $customer = $this->authCustomer();
        $store = $this->makeStore();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::General,
            'status' => OrderStatus::Pending,
        ]);

        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/offers/{$offer->id}/reject", [
                'rejection_reason' => 'Too expensive',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $offer->id)
            ->assertJsonPath('data.status', OfferStatus::Rejected->value)
            ->assertJsonPath('data.rejection_reason', 'Too expensive');

        $this->assertSame(OfferStatus::Rejected, $offer->fresh()->status);
        $this->assertSame('Too expensive', $offer->fresh()->rejection_reason);
    }

    public function test_customer_cannot_reject_another_customers_offer(): void
    {
        $owner = $this->authCustomer();
        $other = $this->authCustomer();
        $store = $this->makeStore();

        $order = Order::factory()->create([
            'customer_id' => $owner->id,
            'order_type' => OrderType::General,
            'status' => OrderStatus::Pending,
        ]);

        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
        ]);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/offers/{$offer->id}/reject", [
                'rejection_reason' => 'No thanks',
            ])
            ->assertForbidden();

        $this->assertSame(OfferStatus::Pending, $offer->fresh()->status);
    }

    public function test_offer_rejection_reason_is_optional(): void
    {
        $customer = $this->authCustomer();
        $store = $this->makeStore();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::General,
            'status' => OrderStatus::Pending,
        ]);

        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/offers/{$offer->id}/reject")
            ->assertOk();

        $this->assertSame(OfferStatus::Rejected, $offer->fresh()->status);
        $this->assertNull($offer->fresh()->rejection_reason);
    }

    public function test_offer_show_returns_offer_with_status(): void
    {
        $customer = $this->authCustomer();
        $store = $this->makeStore();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::General,
            'status' => OrderStatus::Pending,
        ]);

        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/customer/orders/{$order->id}/offers/{$offer->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $offer->id)
            ->assertJsonPath('data.status', OfferStatus::Pending->value);
    }
}