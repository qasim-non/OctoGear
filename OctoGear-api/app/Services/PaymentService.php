<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Enums\PaymentStatus;
use Illuminate\Support\Facades\Log;

/**
 * Handles charging the customer for an order.
 *
 * IMPORTANT (gateway integration):
 *   - When payment_method = cash, no external call is made — the payment is
 *     recorded as paid immediately (cash on pickup/delivery).
 *   - When payment_method = credit_card, the card is charged through a payment
 *     gateway. No real gateway is wired yet; charge() uses a clearly-marked
 *     STUB that simulates a successful charge.
 *
 * To integrate a real gateway (e.g. Moyasar / Tap / PayTabs / HyperPay):
 *   1. Add credentials to .env (never hardcode).
 *   2. Add a `config/payments.php` with the driver + secrets.
 *   3. Replace the body of chargeCard() with a real HTTP call and map the
 *      gateway response to PaymentStatus.
 */
class PaymentService
{
    /**
     * The total amount due for an order.
     */
    public function amountFor(Order $order): int
    {
        return ($order->offered_price ?? 0) * max(1, (int) $order->quantity);
    }

    /**
     * Charge the customer for the order and create a Payment record.
     *
     * Returns the created Payment with a status of Paid (both cash and the
     * credit-card stub resolve to Paid so the order can move to "paid").
     */
    public function charge(Order $order, PaymentMethod $method, ?string $cardToken = null): Payment
    {
        $amount = $this->amountFor($order);

        if ($method === PaymentMethod::CreditCard) {
            $status = $this->chargeCard($order, $amount, $cardToken);

            return $order->payment()->create([
                'amount'         => $amount,
                'payment_method' => PaymentMethod::CreditCard,
                'payment_status' => $status,
            ]);
        }

        return $order->payment()->create([
            'amount'         => $amount,
            'payment_method' => PaymentMethod::Cash,
            'payment_status' => PaymentStatus::Paid,
        ]);
    }

    /**
     * STUB — simulate charging a card through a payment gateway.
     *
     * TODO: Replace with a real external payment gateway call.
     * Real gateways return pending/paid/failed asynchronously; wire the
     * driver from config('payments.driver') and map responses here.
     */
    private function chargeCard(Order $order, int $amount, ?string $cardToken): PaymentStatus
    {
        $driver = config('payments.driver', 'stub');

        Log::info('Card payment attempted (stub)', [
            'driver'   => $driver,
            'order_id' => $order->id,
            'amount'   => $amount,
            'has_token' => $cardToken !== null,
        ]);

        if ($driver === 'stub') {
            return PaymentStatus::Paid;
        }

        // Real gateway integration goes here, e.g.:
        // $res = $this->gateway($driver)->authorize($amount, $cardToken, $order);
        // return $res->approved ? PaymentStatus::Paid : PaymentStatus::Failed;

        return PaymentStatus::Paid;
    }
}
