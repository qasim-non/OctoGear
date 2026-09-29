<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\StoreStatus;
use App\Events\OrderCompleted;
use App\Events\OrderCreated;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\StoreCarComponent;
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
        $created = false;
        $key = $data['idempotency_key'] ?? null;
        $type = $data['order_type'] instanceof OrderType ? $data['order_type']->value : $data['order_type'];
        $fingerprint = $key === null ? null : hash('sha256', json_encode([
            $type,
            (int) ($data['store_car_component_id'] ?? 0),
            (int) ($data['model_id'] ?? 0),
            (int) $data['quantity'],
            trim($data['notes'] ?? ''),
            isset($data['customer_image']) ? hash_file('sha256', $data['customer_image']->getRealPath()) : null,
        ], JSON_THROW_ON_ERROR));

        try {
            $order = DB::transaction(function () use ($customer, $data, $key, $type, $fingerprint, &$storedFiles, &$created) {
                // Serialize this customer's submissions, including concurrent
                // retries, before checking the retained order's request key.
                if ($key !== null) {
                    User::whereKey($customer->id)->lockForUpdate()->firstOrFail();
                    $existing = Order::withTrashed()->where('customer_id', $customer->id)
                        ->where('idempotency_key', $key)->first();
                    if ($existing) {
                        if ($existing->trashed() || ! hash_equals($existing->idempotency_fingerprint ?? '', $fingerprint)) {
                            throw new BusinessRuleException(
                                messageKey: 'auth.validation.idempotency_key.conflict', statusCode: 409,
                            );
                        }

                        return $existing;
                    }
                }

                if ($type === OrderType::Specific->value) {
                    $this->validateRequestedComponent($data);
                }

                $order = $customer->orders()->create([
                    ...Arr::only($data, ['order_type', 'quantity', 'notes', 'store_car_component_id', 'model_id']),
                    'status' => OrderStatus::Pending,
                    'idempotency_key' => $key,
                    'idempotency_fingerprint' => $fingerprint,
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

                $created = true;

                return $order;
            });
        } catch (Throwable $exception) {
            $this->images->cleanup($storedFiles);

            throw $exception;
        }

        // Notify consumers only after the order and its image metadata commit.
        if ($created) {
            OrderCreated::dispatch($order);
        }

        return $order;
    }

    private function validateRequestedComponent(array $data): void
    {
        $component = StoreCarComponent::with(['component', 'storeCar.store'])
            ->lockForUpdate()->find($data['store_car_component_id']);
        $error = null;
        if (! $component || ! $component->component || ! $component->storeCar
            || $component->storeCar->store?->status !== StoreStatus::Active) {
            $error = __('auth.validation.store_car_component.not_found');
        } elseif ($component->stock_quantity < 1) {
            $error = __('auth.validation.store_car_component.out_of_stock');
        } elseif ((int) $data['quantity'] > $component->stock_quantity) {
            $error = __('auth.validation.store_car_component.insufficient_stock', ['stock' => $component->stock_quantity]);
        }

        if ($error !== null) {
            throw new BusinessRuleException(
                messageKey: 'auth.general.validation_failed', statusCode: 422,
                errors: ['store_car_component_id' => [$error]],
            );
        }
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
