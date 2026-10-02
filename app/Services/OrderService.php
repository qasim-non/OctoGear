<?php

namespace App\Services;

use App\Enums\OfferStatus;
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
 *  - selecting a provider offer (status -> AwaitingPayment)
 *  - cancellation (status -> Cancelled, plus offer cleanup)
 *  - receipt confirmation (status -> Completed)
 *  - provider rejection of a specific order
 *
 * State transitions are validated against OrderStatus::canTransitionTo so no
 * illegal transition can ever be persisted.
 */
class OrderService
{
    public function __construct(private ImageStorageService $images, private GeneralOrderDetailsService $generalDetails) {}

    public function createForCustomer(User $customer, array $data): Order
    {
        $storedFiles = [];
        $created = false;
        $key = $data['idempotency_key'] ?? null;
        $type = $data['order_type'] instanceof OrderType ? $data['order_type']->value : $data['order_type'];
        $imageFingerprint = array_map(fn ($file) => [
            hash_file('sha256', $file->getRealPath()), $file->getMimeType(), $file->getSize(),
        ], array_values($data['images'] ?? []));
        $fingerprintData = $type === OrderType::General->value ? [
            $type,
            (int) ($data['customer_car_id'] ?? 0),
            (int) ($data['vehicle']['car_name_id'] ?? 0),
            (int) ($data['vehicle']['manufacturing_year'] ?? 0),
            (int) ($data['vehicle']['color_id'] ?? 0),
            (int) ($data['vehicle']['fuel_type'] ?? 0),
            $data['vehicle']['transmission_type'] ?? null,
            (bool) ($data['save_to_my_cars'] ?? false),
            (int) ($data['component_id'] ?? 0),
            trim($data['component_name'] ?? ''),
            trim($data['description'] ?? ''),
            $imageFingerprint,
        ] : [
            $type,
            (int) ($data['store_car_component_id'] ?? 0),
            (int) $data['quantity'],
            trim($data['notes'] ?? ''),
            $imageFingerprint,
        ];
        $fingerprint = $key === null ? null : hash('sha256', json_encode($fingerprintData, JSON_THROW_ON_ERROR));

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

                $requestedUnitPrice = $type === OrderType::Specific->value
                    ? $this->validateRequestedComponent($data)->price : null;

                $order = $customer->orders()->create([
                    ...($type === OrderType::General->value ? [
                        'order_type' => OrderType::General,
                        // Internal compatibility value until the payment schema slice.
                        'quantity' => 1,
                        'notes' => $data['description'] ?? null,
                    ] : Arr::only($data, ['order_type', 'quantity', 'notes', 'store_car_component_id'])),
                    'status' => OrderStatus::Pending,
                    'requested_unit_price' => $requestedUnitPrice,
                    'idempotency_key' => $key,
                    'idempotency_fingerprint' => $fingerprint,
                ]);

                if ($type === OrderType::General->value) {
                    $this->generalDetails->attach($order, $customer, $data);
                }

                foreach (array_values($data['images'] ?? []) as $position => $file) {
                    $image = $this->images->store(
                        $file,
                        "orders/{$customer->id}/{$order->id}",
                        $storedFiles,
                    );
                    $order->images()->create([...$image, 'sort_order' => $position]);
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

    private function validateRequestedComponent(array $data): StoreCarComponent
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

        return $component;
    }

    /** Select one final total offer and close every competing offer atomically. */
    public function acceptOffer(Order $order, OrderOffer $offer): Order
    {
        return DB::transaction(function () use ($order, $offer): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($lockedOrder->isGeneral() && $lockedOrder->status === OrderStatus::AwaitingPayment) {
                if ((int) $lockedOrder->accepted_offer_id === (int) $offer->id) {
                    return $lockedOrder;
                }
                throw new BusinessRuleException('Another offer was already selected.', 'auth.validation.order.cannot_accept_offer', statusCode: 409);
            }
            if (! $lockedOrder->isGeneral() || ! $lockedOrder->status->canTransitionTo(OrderStatus::AwaitingPayment)) {
                throw new BusinessRuleException('Cannot accept offer for this order.', 'auth.validation.order.cannot_accept_offer', statusCode: 409);
            }

            $pendingOffers = $lockedOrder->offers()
                ->where('status', OfferStatus::Pending->value)
                ->lockForUpdate()
                ->get();
            $selected = $pendingOffers->firstWhere('id', $offer->id);
            if (! $selected || (int) $selected->order_id !== (int) $lockedOrder->id) {
                throw new BusinessRuleException('Cannot accept offer for this order.', 'auth.validation.order.cannot_accept_offer', statusCode: 409);
            }

            $lockedOrder->update([
                'status' => OrderStatus::AwaitingPayment,
                'accepted_offer_id' => $selected->id,
                'accepted_store_id' => $selected->store_id,
                'offered_price' => $selected->price,
            ]);
            $selected->update(['status' => OfferStatus::Accepted]);

            foreach ($pendingOffers->where('id', '!=', $selected->id) as $otherOffer) {
                $otherOffer->update(['status' => OfferStatus::NotSelected]);
            }

            return $lockedOrder->refresh();
        });
    }

    /**
     * Cancel an order and remove its outstanding offers atomically.
     */
    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (! $lockedOrder->status->canTransitionTo(OrderStatus::Cancelled)) {
                throw new BusinessRuleException('This order cannot be cancelled.', 'auth.validation.order.cannot_cancel');
            }
            $lockedOrder->update(['status' => OrderStatus::Cancelled]);
            $lockedOrder->offers()->delete();

            return $lockedOrder->refresh();
        });
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
