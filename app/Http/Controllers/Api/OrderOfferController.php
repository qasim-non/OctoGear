<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OrderOffer\RejectOfferRequest;
use App\Http\Resources\OrderOfferResource;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Services\OrderOfferService;
use Illuminate\Http\Request;

class OrderOfferController extends Controller
{
    public function __construct(private OrderOfferService $offers) {}

    public function index(Request $request, Order $order)
    {
        $this->authorize('viewAny', [OrderOffer::class, $order]);

        $offers = $order->offers()
            ->latest()
            ->paginate(15);

        return $this->paginated($offers->through(fn ($offer) => new OrderOfferResource($offer)));
    }

    public function show(Request $request, Order $order, OrderOffer $offer)
    {
        $this->authorize('view', $offer);

        // Verify the offer belongs to this order
        if ($offer->order_id !== $order->id) {
            return $this->notFound(__('auth.general.not_found'));
        }

        return $this->success(new OrderOfferResource($offer));
    }

    public function reject(RejectOfferRequest $request, Order $order, OrderOffer $offer)
    {
        $this->authorize('update', $offer);

        if ($offer->order_id !== $order->id) {
            return $this->notFound(__('auth.general.not_found'));
        }

        $offer = $this->offers->reject($offer, $request->validated('rejection_reason'));

        return $this->success(new OrderOfferResource($offer));
    }
}
