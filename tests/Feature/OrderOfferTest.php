<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\User;
use App\Services\PaymentService;
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
            'order_type' => OrderType::General,
            'status' => $status,
        ]);

        OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
        ]);

        return $order;
    }

    public function test_can_view_offers_for_a_paid_order(): void
    {
        $customer = $this->authCustomer();
        $order = $this->orderWithOffer($customer, OrderStatus::Paid);
        $offer = $order->offers()->first();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/customer/orders/{$order->id}/offers")
            ->assertOk()->assertJsonPath('data.0.id', $offer->id);

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/customer/orders/{$order->id}/offers/{$offer->id}")
            ->assertOk()->assertJsonPath('data.id', $offer->id);
    }

    public function test_can_view_offers_for_a_completed_order(): void
    {
        $customer = $this->authCustomer();
        $order = $this->orderWithOffer($customer, OrderStatus::Completed);
        $offer = $order->offers()->first();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/customer/orders/{$order->id}/offers")
            ->assertOk()->assertJsonPath('data.0.id', $offer->id);

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/customer/orders/{$order->id}/offers/{$offer->id}")
            ->assertOk()->assertJsonPath('data.id', $offer->id);
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

    public function test_can_view_offers_for_an_order_awaiting_payment(): void
    {
        $customer = $this->authCustomer();
        $order = $this->orderWithOffer($customer, OrderStatus::AwaitingPayment);
        $offer = $order->offers()->first();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/customer/orders/{$order->id}/offers")
            ->assertOk();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/customer/orders/{$order->id}/offers/{$offer->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $offer->id);
    }

    public function test_accepting_an_offer_locks_the_total_and_closes_competing_offers(): void
    {
        $customer = $this->authCustomer();
        $chosenStore = $this->makeStore();
        $otherStore = $this->makeStore();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::General,
            'status' => OrderStatus::Pending,
            'quantity' => 37,
        ]);
        $chosen = OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => $chosenStore->id, 'price' => 30000]);
        $other = OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => $otherStore->id, 'price' => 25000]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/accept-offer", ['offer_id' => $chosen->id])
            ->assertOk()->assertJsonPath('data.accepted_offer_id', $chosen->id)
            ->assertJsonPath('data.status', OrderStatus::AwaitingPayment->value)
            ->assertJsonPath('data.offered_price', 30000);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/accept-offer", ['offer_id' => $chosen->id])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::AwaitingPayment->value)
            ->assertJsonPath('data.accepted_offer_id', $chosen->id)
            ->assertJsonPath('data.offered_price', 30000)
            ->assertJsonPath('data.offers.0.status', OfferStatus::Accepted->value)
            ->assertJsonPath('data.offers.1.status', OfferStatus::NotSelected->value);

        $this->assertSame($chosen->id, $order->fresh()->acceptedOffer->id);
        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/offers/{$other->id}/reject")
            ->assertForbidden();
        $this->actingAs($otherStore->owner, 'sanctum')
            ->putJson("/api/provider/orders/{$order->id}/offer/{$other->id}", ['price' => 1])
            ->assertForbidden();
        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/accept-offer", ['offer_id' => $other->id])
            ->assertStatus(409);

        $this->assertSame(30000, app(PaymentService::class)->amountFor($order->fresh()));
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
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
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
