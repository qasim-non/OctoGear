<?php

namespace App\Services;

use App\Enums\OfferStatus;
use App\Events\OfferCreated;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\OrderOffer;

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

        if ($order->offers()->where('store_id', $storeId)->exists()) {
            throw new BusinessRuleException('You have already submitted an offer on this order.', 'auth.validation.order.already_offered');
        }

        $offer = $order->offers()->create([
            ...$data,
            'store_id' => $storeId,
        ]);

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
        if ($offer->status !== OfferStatus::Pending) {
            throw new BusinessRuleException('This offer cannot be edited.', 'auth.validation.order.cannot_edit_offer');
        }

        $offer->update($data);

        return $offer;
    }

    /**
     * Delete a still-pending offer.
     *
     * @throws BusinessRuleException when the offer is no longer pending
     */
    public function delete(OrderOffer $offer): void
    {
        if ($offer->status !== OfferStatus::Pending) {
            throw new BusinessRuleException('This offer cannot be deleted.', 'auth.validation.order.cannot_delete_offer');
        }

        $offer->delete();
    }

    /**
     * Reject an offer with an optional reason.
     */
    public function reject(OrderOffer $offer, ?string $reason): OrderOffer
    {
        $offer->update([
            'status' => OfferStatus::Rejected,
            'rejection_reason' => $reason,
        ]);

        return $offer;
    }
}
