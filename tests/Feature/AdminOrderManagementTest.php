<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StoreCarComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private function authAdmin(): Admin
    {
        return Admin::factory()->create();
    }

    private function customer(array $attributes = []): User
    {
        return User::factory()->customer()->create([
            'full_name' => 'Ahmed Ali',
            'mobile' => fake()->unique()->numerify('055#######'),
            ...$attributes,
        ]);
    }

    private function makeOrder(User $customer, array $attributes = []): Order
    {
        return Order::factory()->create([
            'customer_id' => $customer->id,
            ...$attributes,
        ]);
    }

    public function test_admin_can_list_all_orders(): void
    {
        $this->makeOrder($this->customer());
        $this->makeOrder($this->customer());

        $this->actingAs($this->authAdmin(), 'sanctum')
            ->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_admin_can_filter_orders_by_status(): void
    {
        $this->makeOrder($this->customer(), ['status' => OrderStatus::Pending]);
        $this->makeOrder($this->customer(), ['status' => OrderStatus::Paid]);

        $this->actingAs($this->authAdmin(), 'sanctum')
            ->getJson('/api/admin/orders?status=paid')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'paid');
    }

    public function test_admin_can_filter_orders_by_type(): void
    {
        $this->makeOrder($this->customer(), ['order_type' => OrderType::General]);
        $this->makeOrder($this->customer(), ['order_type' => OrderType::Specific]);

        $this->actingAs($this->authAdmin(), 'sanctum')
            ->getJson('/api/admin/orders?type=specific')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.order_type', 'specific');
    }

    public function test_admin_can_search_orders_by_customer_name(): void
    {
        $this->makeOrder($this->customer(['full_name' => 'Noura Khalid']));
        $this->makeOrder($this->customer(['full_name' => 'Sara Ahmed']));

        $this->actingAs($this->authAdmin(), 'sanctum')
            ->getJson('/api/admin/orders?customer=Noura')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.customer.full_name', 'Noura Khalid');
    }

    public function test_admin_can_search_orders_by_customer_mobile(): void
    {
        $this->makeOrder($this->customer(['mobile' => '0551111111']));
        $this->makeOrder($this->customer(['mobile' => '0552222222']));

        $this->actingAs($this->authAdmin(), 'sanctum')
            ->getJson('/api/admin/orders?mobile=0552222222')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.customer.mobile', '0552222222');
    }

    public function test_admin_can_view_a_general_order_detail(): void
    {
        $order = $this->makeOrder($this->customer(), ['order_type' => OrderType::General]);

        $this->actingAs($this->authAdmin(), 'sanctum')
            ->getJson("/api/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.customer.full_name', 'Ahmed Ali')
            ->assertJsonStructure(['data' => ['customer', 'payment', 'offers', 'accepted_store']]);
    }

    public function test_admin_can_view_a_paid_specific_order_with_payment(): void
    {
        $order = $this->makeOrder($this->customer(), [
            'order_type' => OrderType::Specific,
            'status' => OrderStatus::Paid,
            'offered_price' => 450,
            'quantity' => 2,
        ]);

        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'amount' => 900,
        ]);

        $this->actingAs($this->authAdmin(), 'sanctum')
            ->getJson("/api/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.payment.amount', 900)
            ->assertJsonPath('data.payment.payment_status', 'paid');
    }

    public function test_admin_can_cancel_a_pending_order(): void
    {
        $order = $this->makeOrder($this->customer(), ['status' => OrderStatus::Pending]);

        $this->actingAs($this->authAdmin(), 'sanctum')
            ->postJson("/api/admin/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value)
            ->assertJsonPath('message', __('auth.admin.orders.cancelled'));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Cancelled->value,
        ]);
    }

    public function test_admin_cannot_cancel_a_paid_order(): void
    {
        $order = $this->makeOrder($this->customer(), ['status' => OrderStatus::Paid]);

        $this->actingAs($this->authAdmin(), 'sanctum')
            ->postJson("/api/admin/orders/{$order->id}/cancel")
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_refund_a_paid_order(): void
    {
        $component = StoreCarComponent::factory()->create(['stock_quantity' => 30]);

        $order = $this->makeOrder($this->customer(), [
            'order_type' => OrderType::Specific,
            'status' => OrderStatus::Paid,
            'quantity' => 5,
            'store_car_component_id' => $component->id,
        ]);

        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'amount' => 1000,
        ]);

        $this->actingAs($this->authAdmin(), 'sanctum')
            ->postJson("/api/admin/orders/{$order->id}/refund")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value)
            ->assertJsonPath('data.payment.payment_status', 'refunded')
            ->assertJsonPath('message', __('auth.admin.orders.refunded'));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Cancelled->value,
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_status' => 'refunded',
        ]);
        $this->assertDatabaseHas('store_car_components', [
            'id' => $component->id,
            'stock_quantity' => 35,
        ]);
    }

    public function test_admin_cannot_refund_an_unpaid_order(): void
    {
        $order = $this->makeOrder($this->customer(), ['status' => OrderStatus::Pending]);

        $this->actingAs($this->authAdmin(), 'sanctum')
            ->postJson("/api/admin/orders/{$order->id}/refund")
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Pending->value,
        ]);
    }

    public function test_refund_is_rejected_when_the_gateway_driver_is_not_implemented(): void
    {
        config(['payments.driver' => 'moyasar']);

        $order = $this->makeOrder($this->customer(), ['status' => OrderStatus::Paid]);

        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'amount' => 1000,
        ]);

        $this->actingAs($this->authAdmin(), 'sanctum')
            ->postJson("/api/admin/orders/{$order->id}/refund")
            ->assertStatus(400)
            ->assertJsonPath('message', __('auth.admin.orders.refund_failed'));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Paid->value,
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_status' => 'paid',
        ]);
    }

    public function test_order_index_requires_a_valid_status(): void
    {
        $this->actingAs($this->authAdmin(), 'sanctum')
            ->getJson('/api/admin/orders?status=unknown')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_guest_cannot_access_admin_orders(): void
    {
        $this->getJson('/api/admin/orders')->assertUnauthorized();
    }

    public function test_customer_cannot_access_admin_orders(): void
    {
        $this->actingAs($this->customer(), 'sanctum')
            ->getJson('/api/admin/orders')
            ->assertStatus(403);
    }

    public function test_blocked_admin_cannot_access_admin_orders(): void
    {
        $blocked = Admin::factory()->create(['status' => 'blocked']);

        $this->actingAs($blocked, 'sanctum')
            ->getJson('/api/admin/orders')
            ->assertStatus(403);
    }
}
