<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StoreCarComponent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Handles charging a customer for an order (credit-card only).
 *
 * DESIGN:
 *   - All payment business logic lives here, not in the controller.
 *   - Every charge is wrapped in a DB transaction so the payment row and the
 *     order status update either both commit or both roll back.
 *   - Exceptions are caught and logged; failures map to PaymentStatus::Failed
 *     and the order is left unchanged.
 *
 * GATEWAY (external API):
 *   The credit card is charged through a payment gateway. No real gateway is
 *   wired yet, so the default driver "stub" simulates a successful charge.
 *
 *   To go live:
 *     1. Set PAYMENT_DRIVER in .env (e.g. moyasar / tap / paytabs / hyperpay).
 *     2. Add its secret keys to .env and to config/payments.php.
 *     3. Implement the driver branch below with a real HTTP (Http::post ...)
 *        call and map the gateway's response to a PaymentStatus.
 */
class PaymentService
{
    /**
     * The total amount due for an order (in the smallest currency unit).
     */
    public function amountFor(Order $order): int
    {
        return ($order->offered_price ?? 0) * max(1, (int) $order->quantity);
    }

    /**
     * The platform commission on an order's gross amount.
     */
    public function commissionFor(Order $order): int
    {
        return (int) round($this->amountFor($order) * config('payments.commission_rate') / 100);
    }

    /**
     * The net amount the provider earns after the platform commission.
     */
    public function netForProvider(Order $order): int
    {
        return $this->amountFor($order) - $this->commissionFor($order);
    }

    /**
     * Commit the sale: reduce the store's stock of the sold part by the ordered
     * quantity.
     *
     * Runs atomically with the payment/order "paid" transition. Only specific
     * orders (those linked to a store_car_component) consume stock; general
     * orders are requests a store bids on, so they consume nothing here.
     *
     * The decrement is conditional (stock >= quantity) and driver-neutral, so
     * stock can never go negative even under concurrent sales.
     */
    private function commitStock(Order $order): void
    {
        $sccId = $order->store_car_component_id;

        if (! $sccId) {
            return;
        }

        $quantity = max(1, (int) $order->quantity);

        StoreCarComponent::query()
            ->whereKey($sccId)
            ->where('stock_quantity', '>=', $quantity)
            ->decrement('stock_quantity', $quantity);
    }

    /**
     * Charge the customer's card and record the payment.
     *
     * DESIGN (pragmatic, production-aware):
     *   1. Deposit a durable "pending" payment row FIRST and commit it — so
     *      there is always a record of the attempt.
     *   2. Charge the card. If the charge itself fails → mark the row "failed"
     *      (audit) and throw, so the user is correctly told it failed.
     *   3. Finalize: mark "paid" + move the order to "paid". If THIS step fails
     *      after the charge already succeeded, the customer has paid but our DB
     *      didn't confirm. Rolling back would lose the charged payment and
     *      falsely tell the user "failed" — the worst outcome. So instead we:
     *        - log a CRITICAL alert (dev must reconcile order manually), and
     *        - still return success, honouring the real-world truth.
     *   4. Dispatch the notification best-effort — it must never break the
     *      request/response.
     *
     * EXTERNAL-GATEWAY SAFETY (when a live gateway is wired): the charge call
     * must be IDEMPOTENT — pass a stable key (order id) so a retried request
     * never charges the card twice.
     *
     * @param  Order  $order
     * @param  string  $paymentMethod  validated payment_method (must be credit_card)
     * @param  string|null  $cardToken  validated card token from the gateway
     * @return Payment  the payment record (paid, or pending awaiting reconcile)
     *
     * @throws RuntimeException when the method is not credit_card, the order is
     *                          already paid / in an unpaid-able state, or the
     *                          gateway charge is rejected.
     */
    public function charge(Order $order, string $paymentMethod, ?string $cardToken = null): Payment
    {
        if ($paymentMethod !== PaymentMethod::CreditCard->value) {
            throw new RuntimeException('Only credit_card payments are supported.');
        }

        if ($order->payment()->exists()) {
            throw new RuntimeException('This order has already been paid.');
        }

        if (! $order->status->canTransitionTo(OrderStatus::Paid)) {
            throw new RuntimeException('This order cannot be paid right now.');
        }

        $amount = $this->amountFor($order);

        try {
            // 1) Durable pending row (single atomic INSERT).
            $payment = $order->payment()->create([
                'amount'         => $amount,
                'payment_method' => PaymentMethod::CreditCard,
                'payment_status' => PaymentStatus::Pending,
            ]);
        } catch (\Throwable $e) {
            Log::error('Could not create pending payment', [
                'order_id' => $order->id,
                'amount'   => $amount,
                'error'    => $e->getMessage(),
            ]);

            throw new RuntimeException('Payment could not be initialized.', 0, $e);
        }

        // 2) Charge the card. A charge failure is definitive: tell the user failed.
        try {
            $this->chargeCard($order, $amount, $cardToken);
        } catch (\Throwable $e) {

                Log::error('Card charge failed', [
                'order_id' => $order->id,
                'amount'   => $amount,
                'error'    => $e->getMessage(),
            ]);

            $payment->update(['payment_status' => PaymentStatus::Failed]);

            throw new RuntimeException('The card charge was not approved.', 0, $e);
        }

        // 3) Finalize. If the DB commit fails AFTER a successful charge, do NOT
        //    roll back or tell the user it failed — the money moved. Log a p0
        //    alert for manual reconciliation and honour the payment.
        try {
            DB::transaction(function () use ($payment, $order) {
                $payment->update(['payment_status' => PaymentStatus::Paid]);
                $order->update(['status' => OrderStatus::Paid]);

                $this->commitStock($order);

                // The order is settled: its offers are no longer relevant,
                // so remove them from the system.
                $order->offers()->delete();
            });
        } catch (\Throwable $e) {
            Log::critical('PAID-BUT-COMMIT-FAILED: reconcile order', [
                'order_id'   => $order->id,
                'payment_id' => $payment->id,
                'amount'     => $amount,
                'error'      => $e->getMessage(),
            ]);
        }

        // 4) Notification is best-effort — never let it break the response.
        try {
            OrderPaid::dispatch($order->fresh());
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch order-paid notification', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }

        return $payment->refresh();
    }

    /**
     * Send the card charge to the (simulated) gateway and return its status.
     *
     * The stub driver always approves so local development can move the order
     * to "paid". Any driver that is configured but not implemented here fails
     * safely (throws) instead of silently approving real charges.
     *
     * NOTE (real gateway): pass the order id as the gateway's IDEMPOTENCY KEY so
     * a retried request can never double-charge the card. e.g.
     *   $this->gateway($driver)->charge([
     *       'amount'         => $amount,
     *       'source'         => $cardToken,
     *       'idempotency_key' => (string) $order->id,
     *   ]);
     */
    private function chargeCard(Order $order, int $amount, ?string $cardToken): PaymentStatus
    {
        $driver = config('payments.driver', 'stub');

        Log::info('Card payment attempted', [
            'driver'    => $driver,
            'order_id'  => $order->id,
            'amount'    => $amount,
            'has_token' => $cardToken !== null,
        ]);

        return match ($driver) {
            'stub' => PaymentStatus::Paid,

            // Real gateway integration goes here, e.g.:
            // 'moyasar' => $this->moyasar()->authorize($amount, $cardToken, $order),
            // 'tap'     => $this->tap()->charge($amount, $cardToken, $order),

            default => throw new RuntimeException(
                "Payment driver [{$driver}] is not implemented."
            ),
        };
    }
}
