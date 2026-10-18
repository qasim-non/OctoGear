<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Data\PaymentCharge;
use App\Data\PaymentRefund;
use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StoreCarComponent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Handles charging a customer for an order (credit-card only).
 *
 * The pending payment is committed before the external operation. Gateway
 * adapters are selected centrally; the default remains a simulation.
 * See INTEGRATIONS.md for the additional work required for live payments.
 */
class PaymentService
{
    public function __construct(private PaymentGateway $gateway, private OrderPricingService $pricing) {}

    /**
     * The whole accepted offer total for general requests; specific purchases
     * continue to multiply their unit price by requested quantity.
     */
    public function amountFor(Order $order): int
    {
        return $this->pricing->amountFor($order);
    }

    /**
     * The platform commission on the order's gross total.
     */
    public function commissionFor(Order $order): int
    {
        return $this->pricing->commissionForAmount($this->amountFor($order));
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

        $component = StoreCarComponent::query()
            ->whereKey($sccId)
            ->first();

        if (! $component || $component->stock_quantity < $quantity) {
            throw new RuntimeException('Insufficient stock for the requested quantity.');
        }

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
     * @param  string  $paymentMethod  validated payment_method (must be credit_card)
     * @param  string|null  $cardToken  validated card token from the gateway
     * @return Payment the payment record (paid, or pending awaiting reconcile)
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

        // Serialize payment initialization with cancellation. The durable row
        // prevents cancellation while the gateway outcome is unresolved.
        $payment = DB::transaction(function () use (&$order): Payment {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->payment()->withTrashed()->exists()) {
                throw new RuntimeException('This order has already been paid.');
            }
            if (! $order->status->canTransitionTo(OrderStatus::Paid)
                || ($order->isGeneral()
                    && (! $order->acceptedOffer
                        || $order->acceptedOffer->order_id !== $order->id
                        || $order->acceptedOffer->status !== OfferStatus::Accepted))) {
                throw new RuntimeException('This order cannot be paid right now.');
            }
            $amount = $this->amountFor($order);

            return $order->payment()->create([
                'amount' => $amount,
                'payment_method' => PaymentMethod::CreditCard,
                'payment_status' => PaymentStatus::Pending,
            ]);
        });
        $amount = $payment->amount;

        // 2) Charge the card. A charge failure is definitive: tell the user failed.
        try {
            $this->chargeCard($order, $amount, $cardToken);
        } catch (\Throwable $e) {

            Log::error('Card charge failed', [
                'order_id' => $order->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
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

                // Retain offers and their images as read-only purchase history.
            });
        } catch (\Throwable $e) {
            Log::critical('PAID-BUT-COMMIT-FAILED: reconcile order', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
        }

        // 4) Notification is best-effort — never let it break the response.
        try {
            OrderPaid::dispatch($order->fresh());
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch order-paid notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $payment->refresh();
    }

    /**
     * Refund a paid order and cancel it.
     *
     * Admin-driven money return: only orders that are paid and carry a paid
     * payment can be refunded. Unlike the charge path (customer → platform),
     * the refund moves money BACK from the platform to the customer's card,
     * so the refund is first sent to the payment gateway (stub driver approves)
     * and only once approved is the DB state flipped — exactly mirroring
     * charge(). If the gateway rejects the refund, nothing is changed locally.
     *
     * Stock taken at charge time is restored and the payment is marked
     * PaymentStatus::Refunded under the same transaction as the order moving
     * to Cancelled.
     *
     * Note the customer-facing cancel path stays untouched (OrderStatus::Paid
     * cannot transition to Cancelled); this is the deliberate admin override.
     *
     * @throws RuntimeException when the gateway rejects the refund
     */
    public function refund(Order $order): Payment
    {
        $payment = $order->payment;

        if ($order->status !== OrderStatus::Paid
            || ! $payment
            || $payment->payment_status !== PaymentStatus::Paid) {
            throw new BusinessRuleException('Only paid orders can be refunded.', 'auth.validation.order.cannot_refund');
        }

        try {
            $this->refundCard($payment);
        } catch (\Throwable $e) {
            Log::error('Card refund failed', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('The card refund was not approved.', 0, $e);
        }

        return DB::transaction(function () use ($order, $payment) {
            $payment->update(['payment_status' => PaymentStatus::Refunded]);
            $order->update(['status' => OrderStatus::Cancelled]);

            $this->restoreStock($order);

            return $payment;
        });
    }

    /** Delegate the external refund without changing local transaction ordering. */
    private function refundCard(Payment $payment): void
    {
        $driver = config('payments.driver', 'stub');

        Log::info('Card refund attempted', [
            'driver' => $driver,
            'order_id' => $payment->order_id,
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
        ]);

        $this->gateway->refund(new PaymentRefund($payment->id, $payment->order_id, $payment->amount));
    }

    /**
     * Restore the stock that commitStock() consumed when the order was paid.
     */
    private function restoreStock(Order $order): void
    {
        if (! $order->store_car_component_id) {
            return;
        }

        $quantity = max(1, (int) $order->quantity);

        StoreCarComponent::query()
            ->whereKey($order->store_car_component_id)
            ->increment('stock_quantity', $quantity);
    }

    /** Delegate the external charge after the pending audit row is committed. */
    private function chargeCard(Order $order, int $amount, ?string $cardToken): void
    {
        $driver = config('payments.driver', 'stub');

        Log::info('Card payment attempted', [
            'driver' => $driver,
            'order_id' => $order->id,
            'amount' => $amount,
            'has_token' => $cardToken !== null,
        ]);

        $this->gateway->charge(new PaymentCharge($order->id, $amount, $cardToken));
    }
}
