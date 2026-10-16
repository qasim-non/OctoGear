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
        return Conversation::query()->where('offer_id', $offer->id)->first();
    }

    public function findForOrder(Order $order): ?Conversation
    {
        return $order->isGeneral()
            ? $this->findForOffer($order->acceptedOffer)
            : Conversation::query()->where('order_id', $order->id)->first();
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
            : Conversation::query()->firstOrCreate(['order_id' => $order->id], [
                'customer_id' => $order->customer_id,
                'provider_id' => $providerId,
            ]);
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
        return Conversation::query()->whereNull('offer_id')->whereNull('order_id')->firstOrCreate([
            'customer_id' => $customerId,
            'provider_id' => $providerId,
        ]);
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
        return Conversation::query()->firstOrCreate(['offer_id' => $offer->id], [
            'customer_id' => $customerId,
            'provider_id' => $providerId,
        ]);
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
