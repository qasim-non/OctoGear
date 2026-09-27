<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Events\OrderCompleted;
use App\Events\OrderCreated;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Owns the order lifecycle and its state transitions.
 *
 * All order business rules live here rather than in the controllers:
 *  - creation (+ side-effect event)
 *  - accepting an offer (status -> Negotiating)
 *  - cancellation (status -> Cancelled, plus offer cleanup)
 *  - receipt confirmation (status -> Completed)
 *  - provider rejection of a specific order
 *
 * State transitions are validated against OrderStatus::canTransitionTo so no
 * illegal transition can ever be persisted.
 */
class OrderService
{
    public function __construct(private ImageStorageService $images) {}

    public function createForCustomer(User $customer, array $data): Order
    {
        $storedFiles = [];

        try {
            $order = DB::transaction(function () use ($customer, $data, &$storedFiles) {
                $order = $customer->orders()->create([
                    ...Arr::except($data, ['customer_image']),
                    'status' => OrderStatus::Pending,
                ]);

                if (isset($data['customer_image'])) {
                    $image = $this->images->store(
                        $data['customer_image'],
                        "orders/{$customer->id}/{$order->id}",
                        $storedFiles,
                    );
                    $order->update([
                        'customer_image_disk' => $image['disk'],
                        'customer_image_path' => $image['path'],
                        'customer_image_mime_type' => $image['mime_type'],
                        'customer_image_size_bytes' => $image['size_bytes'],
                    ]);
                }

                return $order;
            });
        } catch (Throwable $exception) {
            $this->images->cleanup($storedFiles);

            throw $exception;
        }

        // Notify consumers only after the order and its image metadata commit.
        OrderCreated::dispatch($order);

        return $order;
    }

    /**
     * Accept a specific offer, moving the order to the negotiating stage.
     */
    public function acceptOffer(Order $order, OrderOffer $offer): Order
    {
        if (! $order->status->canTransitionTo(OrderStatus::Negotiating)) {
            throw new BusinessRuleException('Cannot accept offer for this order.', 'auth.validation.order.cannot_accept_offer');
        }

        $order->update([
            'status' => OrderStatus::Negotiating,
            'offered_price' => $offer->price,
            'accepted_store_id' => $offer->store_id,
        ]);

        return $order;
    }

    /**
     * Cancel an order and remove its outstanding offers atomically.
     */
    public function cancel(Order $order): Order
    {
        if (! $order->status->canTransitionTo(OrderStatus::Cancelled)) {
            throw new BusinessRuleException('This order cannot be cancelled.', 'auth.validation.order.cannot_cancel');
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => OrderStatus::Cancelled]);
            $order->offers()->delete();
        });

        return $order;
    }

    /**
     * Confirm receipt of an order's delivery (status -> Completed).
     */
    public function complete(Order $order): Order
    {
        if (! $order->status->canTransitionTo(OrderStatus::Completed)) {
            throw new BusinessRuleException('This order cannot be marked as received.', 'auth.validation.order.cannot_complete');
        }

        $order->update(['status' => OrderStatus::Completed]);

        OrderCompleted::dispatch($order);

        return $order;
    }

    /**
     * A provider rejects a specific order that targeted their store.
     */
    public function reject(Order $order): Order
    {
        if ($order->order_type !== OrderType::Specific) {
            throw new BusinessRuleException('Cannot reject a general order.', 'auth.validation.order.cannot_reject_general');
        }

        if (! $order->status->canTransitionTo(OrderStatus::Rejected)) {
            throw new BusinessRuleException('Cannot reject a non-pending order.', 'auth.validation.order.cannot_reject_not_pending');
        }

        $order->update(['status' => OrderStatus::Rejected]);

        return $order;
    }
}
