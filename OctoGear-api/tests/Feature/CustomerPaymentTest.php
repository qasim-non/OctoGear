<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
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

    private function negotiatingOrderFor(User $customer): Order
    {
        return Order::factory()->create([
            'customer_id'       => $customer->id,
            'order_type'        => OrderType::Specific,
            'status'            => OrderStatus::Negotiating,
            'offered_price'     => 450,
            'quantity'          => 1,
            'accepted_store_id' => $this->createStoreWithOwner()->id,
        ]);
    }

    public function test_customer_can_pay_for_a_negotiating_order_with_a_credit_card(): void
    {
        $customer = $this->authCustomer();
        $order = $this->negotiatingOrderFor($customer);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'credit_card',
                'card_token'     => 'tok_test_123',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.payment.payment_status', 'paid');

        $orderData = $response->json('data.order');
        $this->assertArrayNotHasKey('offers', $orderData, 'Offer list should not be loaded at payment time.');
        $this->assertArrayHasKey('accepted_store', $orderData);

        $this->assertDatabaseHas('payments', [
            'order_id'       => $order->id,
            'payment_method' => 'credit_card',
            'payment_status' => 'paid',
            'amount'         => 450,
        ]);

        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => OrderStatus::Paid->value,
        ]);
    }

    public function test_credit_card_is_required_for_card_payment(): void
    {
        $customer = $this->authCustomer();
        $order = $this->negotiatingOrderFor($customer);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'credit_card',
            ]);

        $response->assertStatus(422);
    }

    public function test_cash_is_not_allowed_on_customer_payment(): void
    {
        $customer = $this->authCustomer();
        $order = $this->negotiatingOrderFor($customer);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'cash',
                'card_token'     => 'tok_test_123',
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_pay_twice(): void
    {
        $customer = $this->authCustomer();
        $order = $this->negotiatingOrderFor($customer);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'credit_card',
                'card_token'     => 'tok_test_1',
            ])->assertOk();

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'credit_card',
                'card_token'     => 'tok_test_2',
            ])->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_customer_can_confirm_receipt_after_payment(): void
    {
        $customer = $this->authCustomer();
        $order = $this->negotiatingOrderFor($customer);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'credit_card',
                'card_token'     => 'tok_test_1',
            ])->assertOk();

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/received");

        $response->assertOk();

        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => OrderStatus::Completed->value,
        ]);
    }

    public function test_cannot_confirm_receipt_before_payment(): void
    {
        $customer = $this->authCustomer();
        $order = $this->negotiatingOrderFor($customer);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/received")
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_failed_charge_marks_payment_failed_and_keeps_order_unpaid(): void
    {
        config(['payments.driver' => 'moyasar']);

        $customer = $this->authCustomer();
        $order = $this->negotiatingOrderFor($customer);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'credit_card',
                'card_token'     => 'tok_test_1',
            ])->assertStatus(400)
            ->assertJsonPath('success', false);

        // A rejected charge persists a "failed" audit row; order is unchanged.
        $this->assertDatabaseHas('payments', [
            'order_id'       => $order->id,
            'payment_method' => 'credit_card',
            'payment_status' => 'failed',
            'amount'         => 450,
        ]);

        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => OrderStatus::Negotiating->value,
        ]);
    }

    public function test_payment_notifies_the_store_owner(): void
    {
        $owner = User::factory()->create(['type' => 'service provider']);
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $customer = $this->authCustomer();

        $order = Order::factory()->create([
            'customer_id'       => $customer->id,
            'order_type'        => OrderType::Specific,
            'status'            => OrderStatus::Negotiating,
            'offered_price'     => 300,
            'quantity'          => 1,
            'accepted_store_id' => $store->id,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'credit_card',
                'card_token'     => 'tok_test_1',
            ])->assertOk();

        $notification = DatabaseNotification::query()
            ->where('notifiable_id', $owner->id)
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame(OrderPaidNotification::class, $notification->type);
    }
}
