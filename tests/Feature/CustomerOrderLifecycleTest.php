<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderCompleted;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class CustomerOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->actingAs(User::factory()->customer()->create(), 'sanctum');
    }

    private function order(OrderStatus $status = OrderStatus::Pending): Order
    {
        return Order::factory()->general()->create(['customer_id' => auth()->id(), 'status' => $status]);
    }

    public function test_actions_follow_server_status_and_payment_summary_is_read_only(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $order = $this->order($status);
            $this->getJson('/api/customer/orders/'.$order->id)->assertOk()
                ->assertJsonPath('data.can_cancel', in_array($status, [OrderStatus::Pending, OrderStatus::AwaitingPayment], true))
                ->assertJsonPath('data.can_confirm_received', $status === OrderStatus::Paid)
                ->assertJsonPath('data.payment_summary', null);
        }
        $paid = $this->order(OrderStatus::Completed);
        $payment = Payment::factory()->paid()->create(['order_id' => $paid->id, 'amount' => 12345]);
        $this->getJson('/api/customer/orders/'.$paid->id)->assertOk()
            ->assertJsonPath('data.payment_summary.id', $payment->id)
            ->assertJsonPath('data.payment_summary.amount', 12345)
            ->assertJsonPath('data.paid_amount', 12345);
    }

    public function test_any_payment_attempt_prevents_cancellation_even_if_soft_deleted(): void
    {
        foreach (PaymentStatus::cases() as $status) {
            $order = $this->order(OrderStatus::AwaitingPayment);
            $payment = Payment::factory()->create(['order_id' => $order->id, 'payment_status' => $status]);
            $this->getJson('/api/customer/orders/'.$order->id)->assertOk()->assertJsonPath('data.can_cancel', false);
            $this->postJson('/api/customer/orders/'.$order->id.'/cancel')->assertStatus(400);
            $payment->delete();
            $this->postJson('/api/customer/orders/'.$order->id.'/cancel')->assertStatus(400);
            $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
        }
    }

    public function test_cancel_is_repeatable_but_cannot_complete_a_cancelled_order(): void
    {
        $order = $this->order(OrderStatus::AwaitingPayment);
        foreach ([1, 2] as $_) {
            $this->postJson('/api/customer/orders/'.$order->id.'/cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
        }
        $this->postJson('/api/customer/orders/'.$order->id.'/received')->assertStatus(400);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_repeated_receipt_notifies_once_and_stale_models_cannot_change_status(): void
    {
        Event::fake([OrderCompleted::class]);
        $order = $this->order(OrderStatus::Paid);
        $stale = $order->fresh();
        foreach ([1, 2] as $_) {
            $this->postJson('/api/customer/orders/'.$order->id.'/received')->assertOk()->assertJsonPath('data.status', 'completed');
        }
        app(OrderService::class)->complete($stale);
        Event::assertDispatchedTimes(OrderCompleted::class, 1);
        $this->postJson('/api/customer/orders/'.$order->id.'/cancel')->assertStatus(400);
    }

    public function test_customer_cannot_change_or_read_another_customers_order(): void
    {
        $order = Order::factory()->general()->create(['customer_id' => User::factory()->customer()->create()->id, 'status' => OrderStatus::Paid]);
        $this->getJson('/api/customer/orders/'.$order->id)->assertForbidden();
        foreach (['received', 'cancel'] as $action) {
            $this->postJson('/api/customer/orders/'.$order->id.'/'.$action)->assertForbidden();
        }
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_payment_reloads_order_before_inserting_attempt_after_cancellation(): void
    {
        $stale = $this->order(OrderStatus::AwaitingPayment);
        app(OrderService::class)->cancel($stale);
        try {
            app(PaymentService::class)->charge($stale, 'credit_card', 'test-only');
            $this->fail('A cancelled order must not start payment.');
        } catch (RuntimeException $error) {
            $this->assertSame('This order cannot be paid right now.', $error->getMessage());
        }
        $this->assertDatabaseCount('payments', 0);
    }
}
