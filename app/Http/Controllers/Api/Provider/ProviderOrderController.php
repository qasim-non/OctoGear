<?php

namespace App\Http\Controllers\Api\Provider;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\CreateProviderOrderOfferRequest;
use App\Http\Requests\Provider\ProviderGeneralOrdersRequest;
use App\Http\Requests\Provider\ProviderOffersRequest;
use App\Http\Requests\Provider\UpdateProviderOrderOfferRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProviderGeneralOrderResource;
use App\Http\Resources\ProviderOrderOfferResource;
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
            : null;

        $orders = Order::query()
            ->with($this->generalRelations($user))
            ->where('order_type', OrderType::General)
            ->where('status', OrderStatus::Pending)
            ->when($cityId, fn ($q) => $q->whereHas(
                'customer',
                fn ($c) => $c->where('city_id', $cityId)
            ))
            ->latest()
            ->orderByDesc('id')
            ->paginate(15);

        return $this->paginated($orders->through(fn ($order) => new ProviderGeneralOrderResource($order)));
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

    public function offers(ProviderOffersRequest $request)
    {
        $user = auth()->user();

        $offers = OrderOffer::query()
            ->with(['order.component', 'store', 'images'])
            ->when($request->filled('order_id'), fn ($q) => $q->where('order_id', $request->integer('order_id')))
            ->whereHas('store', fn ($s) => $s->where('user_id', $user->id))
            ->latest()->orderByDesc('id')
            ->paginate(15);

        return $this->paginated($offers->through(fn ($offer) => new ProviderOrderOfferResource($offer)));
    }

    public function showOffer(OrderOffer $offer)
    {
        // Own historical offers stay readable even after another provider wins.
        // Do not serialize the order or its winning offer here.
        abort_unless($offer->store?->user_id === auth()->id(), 404);

        return $this->success(new ProviderOrderOfferResource($offer->load(['store', 'images', 'order.component'])));
    }

    public function paidOrders(PaymentService $payments)
    {
        $user = auth()->user();

        $orders = Order::query()
            ->with(['customer', 'vehicleDetails', 'images', 'component', 'acceptedOffer.store', 'storeCarComponent.storeCar.store'])
            ->whereIn('status', [OrderStatus::Paid])
            ->where(function ($q) use ($user) {
                $q->whereHas('acceptedOffer.store', fn ($store) => $store->where('user_id', $user->id))
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

        return $this->success($order->isGeneral() ? new ProviderGeneralOrderResource($order) : new OrderResource($order));
    }

    private function generalRelations($user): array
    {
        return [
            'customer:id,city_id', 'customer.city:id,name_en,name_ar', 'vehicleDetails', 'images', 'component',
            'offers' => fn ($q) => $q
                ->whereHas('store', fn ($s) => $s->where('user_id', $user->id))
                ->with(['store', 'images']),
            'acceptedOffer.store',
        ];
    }

    private function specificRelations(): array
    {
        return [
            'images',
            'storeCarComponent.storeCar.store',
            'acceptedOffer.store',
        ];
    }

    public function storeOffer(CreateProviderOrderOfferRequest $request, Order $order)
    {
        $user = auth()->user();

        $this->authorize('create', [OrderOffer::class, $order]);

        $offer = $this->offers->create($order, $request->validated());

        $offer->load(['store', 'images', 'order.component']);

        return $this->created(new ProviderOrderOfferResource($offer));
    }

    public function updateOffer(UpdateProviderOrderOfferRequest $request, Order $order, OrderOffer $offer)
    {
        $user = auth()->user();

        $this->authorize('update', $offer);

        if ($offer->order_id !== $order->id) {
            return $this->notFound(__('auth.general.not_found'));
        }

        $data = $request->validated();
        if ($request->boolean('clear_images')) {
            $data['images'] = [];
        }
        unset($data['clear_images']);
        $offer = $this->offers->update($offer, $data);

        $offer->load(['store', 'images', 'order.component']);

        return $this->success(new ProviderOrderOfferResource($offer));
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

        return $this->success(new OrderResource($order->loadMissing('images')));
    }
}
