<?php

namespace App\Repositories;

use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ConversationRepository
{
    private const DISPLAY_RELATIONS = ['customer', 'provider', 'offer.store', 'order.storeCarComponent.storeCar.store'];

    public function findForOffer(OrderOffer $offer): ?Conversation
    {
        $providerId = $offer->store?->user_id ?? $offer->store()->withTrashed()->value('user_id');

        // A transferred store must never transfer an existing private history.
        $original = Conversation::query()->where('offer_id', $offer->id)->first();
        if ($original && $original->provider_id !== $providerId) {
            return $original;
        }

        return $providerId === null ? null : $this->findPair($offer->order->customer_id, $providerId);
    }

    public function findForOrder(Order $order): ?Conversation
    {
        if ($order->isGeneral()) {
            return $this->findForOffer($order->acceptedOffer);
        }
        $providerId = $order->fulfillmentStore()->user_id;
        $original = Conversation::query()->where('order_id', $order->id)->first();

        return $original && $original->provider_id !== $providerId
            ? $original
            : $this->findPair($order->customer_id, $providerId);
    }

    public function loadOrderContext(Order $order, bool $lock = false): Order
    {
        return Order::query()
            ->with(['customer', 'acceptedOffer.store', 'storeCarComponent.storeCar.store'])
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->findOrFail($order->id);
    }

    public function firstOrCreateForOrder(Order $order, int $providerId): Conversation
    {
        return $order->isGeneral()
            ? $this->firstOrCreateForOffer($order->acceptedOffer, $order->customer_id, $providerId)
            : $this->firstOrCreatePair($order->customer_id, $providerId, ['order_id' => $order->id]);
    }

    public function loadOfferContext(OrderOffer $offer): OrderOffer
    {
        return $offer->load(['order.customer', 'store.owner']);
    }

    public function loadRealtimeSummary(Conversation $conversation): Conversation
    {
        return $conversation->load(self::DISPLAY_RELATIONS)
            ->load($this->latestMessageRelation())
            ->loadCount([
                'unreadMessages as customer_unread' => fn ($query) => $query->where('sender_id', '!=', $conversation->customer_id),
                'unreadMessages as provider_unread' => fn ($query) => $query->where('sender_id', '!=', $conversation->provider_id),
            ]);
    }

    public function loadForDisplay(Conversation $conversation, User $viewer, bool $withActivity = true): Conversation
    {
        $conversation->load(self::DISPLAY_RELATIONS);

        if ($withActivity) {
            $conversation->load($this->latestMessageRelation());
            $conversation->loadCount([
                'unreadMessages' => fn ($query) => $query->where('sender_id', '!=', $viewer->id),
            ]);
        }

        return $conversation;
    }

    public function paginateFor(User $viewer, bool $withMessages, int $page): LengthAwarePaginator
    {
        return Conversation::query()
            ->where($viewer->isProvider() ? 'provider_id' : 'customer_id', $viewer->id)
            ->with(self::DISPLAY_RELATIONS)
            ->when($withMessages, fn ($query) => $query->whereHas('messages'))
            ->withLatestMessage()
            ->withUnreadCount($viewer)
            ->withMax('messages', 'id')
            ->orderByDesc('messages_max_id')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'page', $page);
    }

    public function firstOrCreateDirect(int $customerId, int $providerId): Conversation
    {
        return $this->firstOrCreatePair($customerId, $providerId);
    }

    /** Call inside the service's transaction. */
    public function lockOffer(int $offerId): OrderOffer
    {
        return OrderOffer::query()->with(['order', 'store'])->lockForUpdate()->findOrFail($offerId);
    }

    /** Call inside the service's transaction. */
    public function lockAcceptedOffer(Order $order): OrderOffer
    {
        // Accepted offers remain part of owned fulfillment history after soft deletion.
        return OrderOffer::withTrashed()->with('store')->where('order_id', $order->id)
            ->lockForUpdate()->findOrFail($order->accepted_offer_id);
    }

    /** Call inside the service's transaction. */
    public function lockConversation(int $conversationId): Conversation
    {
        return Conversation::query()->with(self::DISPLAY_RELATIONS)->lockForUpdate()->findOrFail($conversationId);
    }

    public function firstOrCreateForOffer(OrderOffer $offer, int $customerId, int $providerId): Conversation
    {
        return $this->firstOrCreatePair($customerId, $providerId, ['offer_id' => $offer->id]);
    }

    private function findPair(int $customerId, int $providerId): ?Conversation
    {
        return Conversation::query()->where('customer_id', $customerId)
            ->where('provider_id', $providerId)->orderBy('id')->first();
    }

    /** All creation callers must own a transaction. */
    private function firstOrCreatePair(int $customerId, int $providerId, array $context = []): Conversation
    {
        $this->lockParticipants($customerId, $providerId);
        if ($context !== []) {
            $original = Conversation::withTrashed()->where($context)->lockForUpdate()->first();
            if ($original && ($original->customer_id !== $customerId || $original->provider_id !== $providerId)) {
                // Leave participant authorization to the service; never reassign history.
                return $original;
            }
        }
        $conversation = Conversation::withTrashed()
            ->where('customer_id', $customerId)->where('provider_id', $providerId)
            ->orderBy('id')->lockForUpdate()->first();
        if ($conversation) {
            if ($conversation->trashed()) {
                $conversation->restore();
            }

            return $conversation;
        }

        return Conversation::create([
            'customer_id' => $customerId, 'provider_id' => $providerId, ...$context,
        ]);
    }

    /** Stable order avoids opposite-role first sends taking opposite user locks. */
    public function lockParticipants(int $customerId, int $providerId): void
    {
        User::query()->whereIn('id', [$customerId, $providerId])
            ->orderBy('id')->lockForUpdate()->get();
    }

    public function touch(Conversation $conversation): void
    {
        $conversation->touch();
    }

    private function latestMessageRelation(): array
    {
        return ['latestMessage' => fn ($query) => $query->limit(1)];
    }
}
