<?php

namespace App\Http\Controllers\Api\Provider;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\CreateProviderOrderOfferRequest;
use App\Http\Requests\Provider\ProviderGeneralOrdersRequest;
use App\Http\Requests\Provider\UpdateProviderOrderOfferRequest;
use App\Http\Resources\OrderOfferResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProviderPaidOrderResource;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Services\OrderOfferService;
use App\Services\OrderService;
use App\Services\PaymentService;

class ProviderOrderController extends Controller
{
    public function __construct(
        private OrderOfferService $offers,
        private OrderService $orders,
    ) {}

    public function general(ProviderGeneralOrdersRequest $request)
    {
        $user = auth()->user();

        $cityId = $request->filled('city_id')
            ? $request->integer('city_id')
            : $user->stores()->value('city_id');

        $orders = Order::query()
            ->with($this->generalRelations($user))
            ->where('order_type', OrderType::General)
            ->where('status', OrderStatus::Pending)
            ->when($cityId, fn ($q) => $q->whereHas(
                'customer',
                fn ($c) => $c->where('city_id', $cityId)
            ))
            ->latest()
            ->paginate(15);

        return $this->paginated($orders->through(fn ($order) => new OrderResource($order)));
    }

    public function specific()
    {
        $user = auth()->user();

        $orders = Order::query()
            ->with($this->specificRelations())
            ->where('order_type', OrderType::Specific)
            ->whereHas('storeCarComponent.storeCar.store', fn ($s) => $s->where('user_id', $user->id))
            ->latest()
            ->paginate(15);

        return $this->paginated($orders->through(fn ($order) => new OrderResource($order)));
    }

    public function offers()
    {
        $user = auth()->user();

        $offers = OrderOffer::query()
            ->with(['order.carModel', 'store'])
            ->whereHas('store', fn ($s) => $s->where('user_id', $user->id))
            ->latest()
            ->paginate(15);

        return $this->paginated($offers->through(fn ($offer) => new OrderOfferResource($offer)));
    }

    public function paidOrders(PaymentService $payments)
    {
        $user = auth()->user();

        $storeIds = $user->stores()->pluck('id');

        $orders = Order::query()
            ->with(['customer', 'carModel', 'acceptedStore', 'storeCarComponent.storeCar.store'])
            ->whereIn('status', [OrderStatus::Paid])
            ->where(function ($q) use ($user, $storeIds) {
                $q->whereIn('accepted_store_id', $storeIds)
                    ->orWhereHas(
                        'storeCarComponent.storeCar.store',
                        fn ($s) => $s->where('user_id', $user->id)
                    );
            })
            ->latest()
            ->paginate(15);

        $orders->getCollection()->transform(function (Order $order) use ($payments) {
            $order->gross_amount = $payments->amountFor($order);
            $order->commission = $payments->commissionFor($order);
            $order->net_amount = $payments->netForProvider($order);

            return $order;
        });

        return $this->paginated(
            $orders->through(fn ($order) => new ProviderPaidOrderResource($order))
        );
    }

    public function show(Order $order)
    {
        $user = auth()->user();

        $this->authorize('view', $order);

        $order->load(
            $order->isGeneral()
                ? $this->generalRelations($user)
                : $this->specificRelations()
        );

        return $this->success(new OrderResource($order));
    }

    private function generalRelations($user): array
    {
        return [
            'carModel',
            'offers' => fn ($q) => $q
                ->whereHas('store', fn ($s) => $s->where('user_id', $user->id))
                ->with('store'),
            'acceptedStore',
        ];
    }

    private function specificRelations(): array
    {
        return [
            'storeCarComponent.storeCar.store',
            'acceptedStore',
        ];
    }

    public function storeOffer(CreateProviderOrderOfferRequest $request, Order $order)
    {
        $user = auth()->user();

        $this->authorize('create', [OrderOffer::class, $order]);

        $offer = $this->offers->create($order, $request->validated());

        $offer->load('store');

        return $this->created(new OrderOfferResource($offer));
    }

    public function updateOffer(UpdateProviderOrderOfferRequest $request, Order $order, OrderOffer $offer)
    {
        $user = auth()->user();

        $this->authorize('update', $offer);

        if ($offer->order_id !== $order->id) {
            return $this->notFound(__('auth.general.not_found'));
        }

        $offer = $this->offers->update($offer, $request->validated());

        $offer->load('store');

        return $this->success(new OrderOfferResource($offer));
    }

    public function destroyOffer(Order $order, OrderOffer $offer)
    {
        $user = auth()->user();

        $this->authorize('delete', $offer);

        if ($offer->order_id !== $order->id) {
            return $this->notFound(__('auth.general.not_found'));
        }

        $this->offers->delete($offer);

        return $this->success(__('auth.general.ok'));
    }

    public function reject(Order $order)
    {
        $user = auth()->user();

        $this->authorize('view', $order);

        $order = $this->orders->reject($order);

        return $this->success(new OrderResource($order));
    }
}
