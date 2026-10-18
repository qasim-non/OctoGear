<?php

namespace App\Repositories;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Models\Conversation;
use App\Models\OrderOffer;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ProviderOfferRepository
{
    /** The caller authorizes the optional conversation before resolving its customer. */
    public function paginateFor(User $provider, int $page, ?int $orderId = null, ?Conversation $conversation = null): LengthAwarePaginator
    {
        return OrderOffer::query()
            ->with(['order.component', 'store', 'images'])
            ->when($orderId !== null, fn ($query) => $query->where('order_id', $orderId))
            ->whereHas('store', fn ($store) => $store->where('user_id', $provider->id))
            ->when($conversation !== null, fn ($query) => $query
                ->where('status', OfferStatus::Pending)
                ->whereHas('order', fn ($order) => $order
                    ->where('customer_id', $conversation->customer_id)
                    ->where('status', OrderStatus::Pending))
                ->when($conversation->offer_id !== null, fn ($offers) => $offers
                    ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$conversation->offer_id])))
            ->latest()->orderByDesc('id')
            ->paginate(15, ['*'], 'page', $page);
    }
}
