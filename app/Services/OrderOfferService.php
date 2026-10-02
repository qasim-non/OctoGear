<?php

namespace App\Services;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Events\OfferCreated;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\OrderOffer;
use Illuminate\Support\Facades\DB;

/**
 * Owns the offer lifecycle on an order.
 *
 * Encapsulates the business rules around offers:
 *  - creation (+ deduplication per store, side-effect event)
 *  - updating / deleting a pending offer
 *  - rejecting an offer with a reason
 *
 * The duplicate-offer rule is enforced both here and (as the final line of
 * defence) by a unique constraint on order_offers(order_id, store_id).
 */
class OrderOfferService
{
    /**
     * Create an offer for a store on an order.
     *
     * @throws BusinessRuleException when the store already has an offer on this order
     */
    public function create(Order $order, array $data): OrderOffer
    {
        $storeId = (int) $data['store_id'];

        $offer = DB::transaction(function () use ($order, $storeId, $data): OrderOffer {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (! $lockedOrder->isGeneral() || $lockedOrder->status !== OrderStatus::Pending) {
                throw new BusinessRuleException('This order is no longer accepting offers.', 'auth.validation.order.cannot_accept_offer');
            }
            if ($lockedOrder->offers()->where('store_id', $storeId)->exists()) {
                throw new BusinessRuleException('You have already submitted an offer on this order.', 'auth.validation.order.already_offered');
            }

            return $lockedOrder->offers()->create([...$data, 'store_id' => $storeId]);
        });

        OfferCreated::dispatch($offer);

        return $offer;
    }

    /**
     * Update a still-pending offer.
     *
     * @throws BusinessRuleException when the offer is no longer pending
     */
    public function update(OrderOffer $offer, array $data): OrderOffer
    {
        return DB::transaction(function () use ($offer, $data): OrderOffer {
            $order = Order::query()->lockForUpdate()->findOrFail($offer->order_id);
            $lockedOffer = OrderOffer::query()->lockForUpdate()->findOrFail($offer->id);
            if ($order->status !== OrderStatus::Pending || $lockedOffer->status !== OfferStatus::Pending) {
                throw new BusinessRuleException('This offer cannot be edited.', 'auth.validation.order.cannot_edit_offer');
            }

            $lockedOffer->update($data);

            return $lockedOffer;
        });
    }

    /**
     * Delete a still-pending offer.
     *
     * @throws BusinessRuleException when the offer is no longer pending
     */
    public function delete(OrderOffer $offer): void
    {
        DB::transaction(function () use ($offer): void {
            $order = Order::query()->lockForUpdate()->findOrFail($offer->order_id);
            $lockedOffer = OrderOffer::query()->lockForUpdate()->findOrFail($offer->id);
            if ($order->status !== OrderStatus::Pending || $lockedOffer->status !== OfferStatus::Pending) {
                throw new BusinessRuleException('This offer cannot be deleted.', 'auth.validation.order.cannot_delete_offer');
            }

            $lockedOffer->delete();
        });
    }

    /**
     * Reject an offer with an optional reason.
     */
    public function reject(OrderOffer $offer, ?string $reason): OrderOffer
    {
        return DB::transaction(function () use ($offer, $reason): OrderOffer {
            $order = Order::query()->lockForUpdate()->findOrFail($offer->order_id);
            $lockedOffer = OrderOffer::query()->lockForUpdate()->findOrFail($offer->id);
            if ($order->status !== OrderStatus::Pending || $lockedOffer->status !== OfferStatus::Pending) {
                throw new BusinessRuleException('This offer can no longer be rejected.', 'auth.validation.order.cannot_accept_offer');
            }

            $lockedOffer->update(['status' => OfferStatus::Rejected, 'rejection_reason' => $reason]);

            return $lockedOffer;
        });
    }
}
