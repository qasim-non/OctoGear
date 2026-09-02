<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\OrderStatus;
use App\Events\OrderCompleted;
use App\Events\OrderCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AcceptOfferRequest;
use App\Http\Requests\Customer\PayOrderRequest;
use App\Http\Requests\Customer\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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

        DB::transaction(function () use ($order) {
            $order->update(['status' => OrderStatus::Cancelled]);

            $order->offers()->delete();
        });

        return $this->success(new OrderResource($order));
    }

    public function pay(PayOrderRequest $request, Order $order, PaymentService $payments)
    {
        $this->authorize('create', Payment::class);
        $this->authorize('update', $order);

        $data = $request->validated();

        try {
            $payment = $payments->charge(
                $order,
                $data['payment_method'],
                $data['card_token'] ?? null,
            );
        } catch (RuntimeException $e) {
            $message = match ($e->getMessage()) {
                'This order has already been paid.' => __('auth.validation.payment.already_paid'),
                'This order cannot be paid right now.' => __('auth.validation.payment.cannot_pay'),
                default => __('auth.validation.payment.gateway_error'),
            };

            return $this->error($message);
        }

        $order->refresh()->load(['carModel', 'storeCarComponent.storeCar.store', 'acceptedStore']);

        return $this->success([
            'payment' => new PaymentResource($payment),
            'order'   => new OrderResource($order),
        ]);
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
}
