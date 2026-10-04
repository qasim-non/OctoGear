<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StoreStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\StoreCarComponent;
use Illuminate\Support\Facades\DB;

/** Customer corrections never change an existing quote or a payment record. */
class CustomerOrderManagement
{
    public function __construct(private GeneralOrderDetailsService $details) {}

    public static function canEdit(Order $order): bool
    {
        return $order->status === OrderStatus::Pending
            && $order->accepted_offer_id === null && ! self::hasPaymentHistory($order)
            && ! ($order->has_offer_history ?? $order->offers()->withTrashed()->exists());
    }

    public static function canDelete(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::Pending, OrderStatus::Rejected, OrderStatus::Cancelled], true)
            && $order->accepted_offer_id === null && ! self::hasPaymentHistory($order);
    }

    private static function hasPaymentHistory(Order $order): bool
    {
        // Removing a payment from normal views must never unlock its order.
        return $order->has_payment_history ?? $order->payment()->withTrashed()->exists();
    }

    public static function token(Order $order): string
    {
        return hash('sha256', json_encode([
            $order->id, $order->status->value, $order->quantity, $order->notes,
            $order->component_id, $order->component_name, $order->accepted_offer_id,
            $order->updated_at?->toISOString(), $order->vehicleDetails?->getAttributes(),
        ], JSON_THROW_ON_ERROR));
    }

    public function update(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (! self::canEdit($locked)) {
                throw new BusinessRuleException(messageKey: 'order_management.cannot_edit', statusCode: 409);
            }
            $this->checkToken($locked, $data['edit_token']);
            if ($locked->isGeneral()) {
                // Omitted vehicle/part fields retain the original snapshot, including retired references.
                if (isset($data['vehicle'])) {
                    $this->details->replaceVehicle($locked, $data['vehicle']);
                }
                if (isset($data['component_id']) || isset($data['component_name'])) {
                    $this->details->attachComponent($locked, $data);
                }
                if (array_key_exists('description', $data)) {
                    $locked->notes = $data['description'];
                }
            } else {
                if (isset($data['quantity']) && $data['quantity'] !== $locked->quantity) {
                    $part = StoreCarComponent::with(['component', 'storeCar.store'])
                        ->lockForUpdate()->find($locked->store_car_component_id);
                    if (! $part?->component || $part->storeCar?->store?->status !== StoreStatus::Active
                        || $part->stock_quantity < $data['quantity']) {
                        throw new BusinessRuleException(messageKey: 'order_management.stock', statusCode: 422);
                    }
                    $locked->quantity = $data['quantity'];
                }
                if (array_key_exists('notes', $data)) {
                    $locked->notes = $data['notes'];
                }
            }
            $locked->save();

            return $locked->fresh();
        });
    }

    public function delete(Order $order, string $token): void
    {
        DB::transaction(function () use ($order, $token) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (! self::canDelete($locked)) {
                throw new BusinessRuleException(messageKey: 'order_management.cannot_delete', statusCode: 409);
            }
            $this->checkToken($locked, $token);
            $locked->offers()->delete();
            // Retain history, image metadata and the original idempotency key.
            $locked->delete();
        });
    }

    private function checkToken(Order $order, string $token): void
    {
        if (! hash_equals(self::token($order), $token)) {
            throw new BusinessRuleException(messageKey: 'order_management.changed', statusCode: 409);
        }
    }
}
