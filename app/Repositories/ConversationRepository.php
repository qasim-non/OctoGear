<?php

namespace App\Repositories;

use App\Models\Conversation;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ConversationRepository
{
    private const DISPLAY_RELATIONS = ['customer', 'provider', 'offer.store'];

    public function findForOffer(OrderOffer $offer): ?Conversation
    {
        return Conversation::query()->where('offer_id', $offer->id)->first();
    }

    public function loadOfferContext(OrderOffer $offer): OrderOffer
    {
        return $offer->load(['order.customer', 'store.owner']);
    }

    public function loadForDisplay(Conversation $conversation, User $viewer, bool $withActivity = true): Conversation
    {
        $conversation->load(self::DISPLAY_RELATIONS);

        if ($withActivity) {
            $conversation->load(['latestMessage' => fn ($query) => $query->limit(1)]);
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
        return Conversation::query()->whereNull('offer_id')->firstOrCreate([
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
}
