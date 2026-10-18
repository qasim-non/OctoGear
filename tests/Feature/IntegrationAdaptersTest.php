<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Contracts\SmsSender;
use App\Data\PaymentCharge;
use App\Data\PaymentRefund;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\OtpService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Tests\TestCase;

class IntegrationAdaptersTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_sms_adapter_receives_the_normalized_login_code_without_local_logging(): void
    {
        $this->app->instance('env', 'local');
        config(['sms.driver' => 'recording', 'sms.drivers.recording' => RecordingSmsSender::class]);
        $sender = new RecordingSmsSender;
        $this->app->instance(RecordingSmsSender::class, $sender);
        Log::spy();

        $this->postJson('/api/auth/otp/send', ['mobile' => '500000001'])->assertOk();

        [$mobile, $code] = $sender->messages[0];
        $this->assertSame('+966500000001', $mobile);
        $this->assertMatchesRegularExpression('/^[0-9]{4}$/', $code);
        $this->assertTrue(Hash::check($code, OtpCode::firstOrFail()->hashed_otp));
        $this->assertTrue($this->app->make(OtpService::class)->verifyOtp($mobile, $code));
        Log::shouldNotHaveReceived('info');
    }

    public function test_unknown_sms_driver_does_not_fall_back_to_simulated_delivery(): void
    {
        config(['sms.driver' => 'missing']);
        $this->expectException(InvalidArgumentException::class);
        $this->app->make(OtpService::class)->sendOtp('+966500000001');
    }

    public function test_configured_payment_adapter_gets_commands_after_the_pending_record_and_preserves_refunds(): void
    {
        Event::fake([OrderPaid::class]);
        config(['payments.driver' => 'recording', 'payments.drivers.recording' => RecordingPaymentGateway::class]);
        $gateway = new RecordingPaymentGateway;
        $this->app->instance(RecordingPaymentGateway::class, $gateway);
        $order = Order::factory()->create([
            'customer_id' => User::factory()->create(['type' => 'customer'])->id,
            'order_type' => OrderType::Specific,
            'status' => OrderStatus::AwaitingPayment,
            'offered_price' => 450,
            'quantity' => 2,
        ]);
        $service = $this->app->make(PaymentService::class);

        $payment = $service->charge($order, 'credit_card', 'test-token');

        $this->assertSame('pending', $gateway->statusAtCharge);
        $this->assertSame(900, $gateway->charge->amount);
        $this->assertSame('SAR', $gateway->charge->currency());
        $this->assertSame('test-token', $gateway->charge->cardToken);
        $this->assertSame('order-'.$order->id.'-charge', $gateway->charge->idempotencyKey());
        $this->assertSame('paid', $payment->payment_status->value);

        $service->refund($order->fresh());

        $this->assertSame($payment->id, $gateway->refund->paymentId);
        $this->assertSame(900, $gateway->refund->amount);
        $this->assertSame('payment-'.$payment->id.'-refund', $gateway->refund->idempotencyKey());
        $this->assertSame('refunded', $payment->fresh()->payment_status->value);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }
}

class RecordingSmsSender implements SmsSender
{
    public array $messages = [];

    public function sendOtp(string $mobile, string $code): void
    {
        $this->messages[] = [$mobile, $code];
    }
}

class RecordingPaymentGateway implements PaymentGateway
{
    public PaymentCharge $charge;

    public PaymentRefund $refund;

    public string $statusAtCharge;

    public function charge(PaymentCharge $charge): void
    {
        $this->charge = $charge;
        $this->statusAtCharge = Order::findOrFail($charge->orderId)->payment->payment_status->value;
    }

    public function refund(PaymentRefund $refund): void
    {
        $this->refund = $refund;
    }
}
