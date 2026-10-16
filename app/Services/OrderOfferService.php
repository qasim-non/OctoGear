<?php

namespace App\Services;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Events\OfferCreated;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\OrderOffer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

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
    public function __construct(private ImageStorageService $images) {}

    /**
     * Create an offer for a store on an order.
     *
     * @throws BusinessRuleException when the store already has an offer on this order
     */
    public function create(Order $order, array $data): OrderOffer
    {
        $storeId = (int) $data['store_id'];
        $imageFiles = array_values($data['images'] ?? []);
        unset($data['images']);
        $storedFiles = [];

        try {
            $offer = DB::transaction(function () use ($order, $storeId, $data, $imageFiles, &$storedFiles): OrderOffer {
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
                if (! $lockedOrder->isGeneral() || $lockedOrder->status !== OrderStatus::Pending) {
                    throw new BusinessRuleException('This order is no longer accepting offers.', 'auth.validation.order.cannot_accept_offer');
                }
                $previous = $lockedOrder->offers()->withTrashed()->where('store_id', $storeId)->first();
                if ($previous && ! $previous->trashed()) {
                    throw new BusinessRuleException('You have already submitted an offer on this order.', 'auth.validation.order.already_offered');
                }

                if ($previous) {
                    // Reuse the unique order/store identity after withdrawal.
                    $this->deleteImages($previous);
                    $previous->fill([...$data, 'notes' => $data['notes'] ?? null, 'status' => OfferStatus::Pending, 'rejection_reason' => null]);
                    $previous->restore();
                    $offer = $previous;
                } else {
                    $offer = $lockedOrder->offers()->create([...$data, 'store_id' => $storeId, 'status' => OfferStatus::Pending]);
                }
                $this->storeImages($offer, $imageFiles, $storedFiles);

                return $offer;
            });
        } catch (Throwable $exception) {
            $this->images->cleanup($storedFiles);

            throw $exception;
        }

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
        $replaceImages = array_key_exists('images', $data);
        $imageFiles = array_values($data['images'] ?? []);
        unset($data['images']);
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($offer, $data, $replaceImages, $imageFiles, &$storedFiles): OrderOffer {
                $lockedOffer = $this->lockPendingOffer(
                    $offer, 'This offer cannot be edited.', 'auth.validation.order.cannot_edit_offer',
                );

                $lockedOffer->update($data);
                if ($replaceImages) {
                    $this->deleteImages($lockedOffer);
                    $this->storeImages($lockedOffer, $imageFiles, $storedFiles);
                }

                return $lockedOffer->refresh();
            });
        } catch (Throwable $exception) {
            $this->images->cleanup($storedFiles);

            throw $exception;
        }
    }

    /**
     * Delete a still-pending offer.
     *
     * @throws BusinessRuleException when the offer is no longer pending
     */
    public function delete(OrderOffer $offer): void
    {
        DB::transaction(function () use ($offer): void {
            $lockedOffer = $this->lockPendingOffer(
                $offer, 'This offer cannot be deleted.', 'auth.validation.order.cannot_delete_offer',
            );

            $lockedOffer->delete();
        });
    }

    /**
     * Reject an offer with an optional reason.
     */
    public function reject(OrderOffer $offer, ?string $reason): OrderOffer
    {
        return DB::transaction(function () use ($offer, $reason): OrderOffer {
            $lockedOffer = $this->lockPendingOffer(
                $offer, 'This offer can no longer be rejected.', 'auth.validation.order.cannot_accept_offer',
            );

            $lockedOffer->update(['status' => OfferStatus::Rejected, 'rejection_reason' => $reason]);

            return $lockedOffer;
        });
    }

    /** @param array<int, UploadedFile> $files
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     */
    private function storeImages(OrderOffer $offer, array $files, array &$storedFiles): void
    {
        foreach ($files as $position => $file) {
            $metadata = $this->images->store(
                $file,
                "offers/{$offer->order_id}/{$offer->id}",
                $storedFiles,
            );
            $offer->images()->create([...$metadata, 'sort_order' => $position]);
        }
    }

    /** Call inside the transaction, locking the order before its offer. */
    private function lockPendingOffer(OrderOffer $offer, string $message, string $messageKey): OrderOffer
    {
        $order = Order::query()->lockForUpdate()->findOrFail($offer->order_id);
        $lockedOffer = OrderOffer::query()->lockForUpdate()->findOrFail($offer->id);
        if ($order->status !== OrderStatus::Pending || $lockedOffer->status !== OfferStatus::Pending) {
            throw new BusinessRuleException($message, $messageKey);
        }

        return $lockedOffer;
    }

    private function deleteImages(OrderOffer $offer): void
    {
        $oldFiles = $offer->images()->get()->map->fileMetadata()->all();
        $offer->images()->delete();
        $this->images->deleteAfterCommit($oldFiles);
    }
}
