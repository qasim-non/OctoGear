<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\StoresCar;
use App\Models\User;
use App\Notifications\OrderPaidNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class CustomerPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function authCustomer(): User
    {
        $user = User::factory()->create(['type' => 'customer']);

        return $user;
    }

    private function createStoreWithOwner(): Store
    {
        $owner = User::factory()->create(['type' => 'service provider']);

        return Store::factory()->create(['user_id' => $owner->id]);
    }

    private function orderAwaitingPaymentFor(User $customer): Order
    {
        return Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::Specific,
            'status' => OrderStatus::AwaitingPayment,
            'offered_price' => 450,
            'quantity' => 1,
        ]);
    }

    public function test_customer_can_pay_for_an_order_awaiting_payment(): void
    {
        $customer = $this->authCustomer();
        $order = $this->orderAwaitingPaymentFor($customer);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'credit_card',
                'card_token' => 'tok_test_123',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.payment.payment_status', 'paid');

        $orderData = $response->json('data.order');
        $this->assertArrayNotHasKey('offers', $orderData, 'Offer list should not be loaded at payment time.');
        $this->assertArrayHasKey('accepted_store', $orderData);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
            'payment_status' => 'paid',
            'amount' => 450,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Paid->value,
        ]);
    }

    public function test_paying_preserves_offers_for_the_order(): void
    {
        $customer = $this->authCustomer();
        $store = $this->createStoreWithOwner();
        $order = $this->orderAwaitingPaymentFor($customer);

        $other = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'credit_card',
                'card_token' => 'tok_test_123',
            ])->assertOk();

        $this->assertDatabaseHas('order_offers', ['id' => $other->id, 'deleted_at' => null]);
    }

    public function test_credit_card_is_required_for_card_payment(): void
    {
        $customer = $this->authCustomer();
        $order = $this->orderAwaitingPaymentFor($customer);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'credit_card',
            ]);

        $response->assertStatus(422);
    }

    public function test_cash_is_not_allowed_on_customer_payment(): void
    {
        $customer = $this->authCustomer();
        $order = $this->orderAwaitingPaymentFor($customer);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'cash',
                'card_token' => 'tok_test_123',
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_pay_twice(): void
    {
        $customer = $this->authCustomer();
        $order = $this->orderAwaitingPaymentFor($customer);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'credit_card',
                'card_token' => 'tok_test_1',
            ])->assertOk();

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'credit_card',
                'card_token' => 'tok_test_2',
            ])->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_customer_can_confirm_receipt_after_payment(): void
    {
        $customer = $this->authCustomer();
        $order = $this->orderAwaitingPaymentFor($customer);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'credit_card',
                'card_token' => 'tok_test_1',
            ])->assertOk();

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/received");

        $response->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Completed->value,
        ]);
    }

    public function test_cannot_confirm_receipt_before_payment(): void
    {
        $customer = $this->authCustomer();
        $order = $this->orderAwaitingPaymentFor($customer);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/received")
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_failed_charge_marks_payment_failed_and_keeps_order_unpaid(): void
    {
        config(['payments.driver' => 'moyasar']);

        $customer = $this->authCustomer();
        $order = $this->orderAwaitingPaymentFor($customer);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'credit_card',
                'card_token' => 'tok_test_1',
            ])->assertStatus(400)
            ->assertJsonPath('success', false);

        // A rejected charge persists a "failed" audit row; order is unchanged.
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
            'payment_status' => 'failed',
            'amount' => 450,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::AwaitingPayment->value,
        ]);
    }

    public function test_payment_notifies_the_store_owner(): void
    {
        $owner = User::factory()->create(['type' => 'service provider']);
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $customer = $this->authCustomer();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::Specific,
            'status' => OrderStatus::AwaitingPayment,
            'offered_price' => 300,
            'quantity' => 1,
            'store_car_component_id' => StoreCarComponent::factory()->create([
                'store_car_id' => StoresCar::factory()->create(['store_id' => $store->id])->id,
            ])->id,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'credit_card',
                'card_token' => 'tok_test_1',
            ])->assertOk();

        $notification = DatabaseNotification::query()
            ->where('notifiable_id', $owner->id)
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame(OrderPaidNotification::class, $notification->type);
    }

    public function test_general_order_payment_uses_accepted_offer_total_once(): void
    {
        $customer = $this->authCustomer();
        $store = $this->createStoreWithOwner();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::General,
            'status' => OrderStatus::AwaitingPayment,
            'offered_price' => 30000,
            'quantity' => 37,
        ]);
        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
            'price' => 30000,
            'status' => OfferStatus::Accepted,
        ]);
        $order->update(['accepted_offer_id' => $offer->id]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'credit_card',
                'card_token' => 'tok_test_1',
            ])
            ->assertOk()
            ->assertJsonPath('data.payment.amount', 30000);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => 30000,
        ]);
    }
}
