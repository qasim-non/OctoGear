<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\OrderStatus;
use App\Events\OrderCompleted;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AcceptOfferRequest;
use App\Http\Requests\Customer\PayOrderRequest;
use App\Http\Requests\Customer\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Services\PaymentService;

class CustomerOrderController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Order::class);

        $orders = auth()->user()
            ->orders()
            ->with(['carModel', 'storeCarComponent.storeCar.store', 'offers.store', 'acceptedStore'])
            ->latest()
            ->paginate(15);

        return $this->paginated($orders->through(fn ($order) => new OrderResource($order)));
    }

    public function store(StoreOrderRequest $request)
    {
        $this->authorize('create', Order::class);

        $order = auth()->user()->orders()->create([
            ...$request->validated(),
            'status' => OrderStatus::Pending,
        ]);

        OrderCreated::dispatch($order);

        return $this->created(new OrderResource($order));
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        $order->load(['carModel', 'storeCarComponent.storeCar.store', 'offers.store', 'acceptedStore']);

        return $this->success(new OrderResource($order));
    }

    public function acceptOffer(AcceptOfferRequest $request, Order $order)
    {
        $this->authorize('update', $order);

        if (!$order->status->canTransitionTo(OrderStatus::Negotiating)) {
            return $this->error(__('auth.validation.order.cannot_accept_offer'));
        }

        $offer = $order->offers()->findOrFail($request->offer_id);

        $order->update([
            'status'             => OrderStatus::Negotiating,
            'offered_price'      => $offer->price,
            'accepted_store_id'  => $offer->store_id,
        ]);

        $order->load(['carModel', 'storeCarComponent.storeCar.store', 'offers.store', 'acceptedStore']);

        return $this->success(new OrderResource($order));
    }

    public function cancel(Order $order)
    {
        $this->authorize('update', $order);

        if (!$order->status->canTransitionTo(OrderStatus::Cancelled)) {
            return $this->error(__('auth.validation.order.cannot_cancel'));
        }

        $order->update(['status' => OrderStatus::Cancelled]);

        return $this->success(new OrderResource($order));
    }

    public function pay(PayOrderRequest $request, Order $order, PaymentService $payments)
    {
        $this->authorize('create', \App\Models\Payment::class);
        $this->authorize('update', $order);

        if (!$order->status->canTransitionTo(OrderStatus::Paid)) {
            return $this->error(__('auth.validation.payment.cannot_pay'));
        }

        if ($order->payment()->exists()) {
            return $this->error(__('auth.validation.payment.already_paid'));
        }

        $payment = $payments->charge(
            $order,
            $request->enum('payment_method', \App\Enums\PaymentMethod::class),
            $request->input('card_token')
        );

        $order->update(['status' => OrderStatus::Paid]);

        OrderPaid::dispatch($order->refresh());

        $order->load(['carModel', 'storeCarComponent.storeCar.store', 'offers.store', 'acceptedStore']);

        return $this->success($this->paymentPayload($payment, $order));
    }

    public function received(Order $order)
    {
        $this->authorize('update', $order);

        if (!$order->status->canTransitionTo(OrderStatus::Completed)) {
            return $this->error(__('auth.validation.order.cannot_complete'));
        }

        $order->update(['status' => OrderStatus::Completed]);

        OrderCompleted::dispatch($order);

        $order->load(['carModel', 'storeCarComponent.storeCar.store', 'offers.store', 'acceptedStore']);

        return $this->success(new OrderResource($order));
    }

    private function paymentPayload($payment, Order $order): array
    {
        return [
            'payment' => new PaymentResource($payment),
            'order'   => new OrderResource($order),
        ];
    }
}
