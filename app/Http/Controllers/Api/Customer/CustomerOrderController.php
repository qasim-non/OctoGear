<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AcceptOfferRequest;
use App\Http\Requests\Customer\CustomerOrdersRequest;
use App\Http\Requests\Customer\PayOrderRequest;
use App\Http\Requests\Customer\StoreOrderRequest;
use App\Http\Requests\Customer\UpdateOrderRequest;
use App\Http\Resources\CustomerOrderResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CustomerOrderManagement;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use RuntimeException;

class CustomerOrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(CustomerOrdersRequest $request)
    {
        $this->authorize('viewAny', Order::class);

        $orders = auth()->user()
            ->orders()
            ->with($this->historyRelations())
            ->with(['offers.store', 'offers.images'])
            ->withCount('offers')
            ->withExists([
                'offers as has_offer_history' => fn ($query) => $query->withTrashed(),
                'payment as has_payment_history' => fn ($query) => $query->withTrashed(),
            ])
            ->when($request->validated('order_type'), fn ($query, $type) => $query->where('order_type', $type))
            ->when($request->validated('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->orderByDesc('id')
            ->paginate(15);

        return $this->paginated($orders->through(fn ($order) => new CustomerOrderResource($order)));
    }

    public function store(StoreOrderRequest $request)
    {
        $this->authorize('create', Order::class);

        $order = $this->orders->createForCustomer($request->user(), $request->validated());

        return $this->created(new OrderResource($order->loadMissing(['vehicleDetails', 'images', 'component'])));
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        $order->load([...$this->historyRelations(), 'offers' => fn ($query) => $query->with(['store', 'images'])->latest()->orderByDesc('id')]);
        $order->loadCount('offers');
        $order->loadExists([
            'offers as has_offer_history' => fn ($query) => $query->withTrashed(),
            'payment as has_payment_history' => fn ($query) => $query->withTrashed(),
        ]);

        return $this->success(new CustomerOrderResource($order));
    }

    public function update(UpdateOrderRequest $request, Order $order, CustomerOrderManagement $management)
    {
        $this->authorize('update', $order);
        $order = $management->update($order, $request->validated());

        return $this->show($order);
    }

    public function destroy(Request $request, Order $order, CustomerOrderManagement $management)
    {
        $this->authorize('delete', $order);
        $data = $request->validate(['edit_token' => ['required', 'string', 'size:64']]);
        $management->delete($order, $data['edit_token']);

        return $this->success(['id' => $order->id, 'deleted' => true]);
    }

    private function historyRelations(): array
    {
        return ['vehicleDetails', 'images', 'component', 'storeCarComponent.component', 'storeCarComponent.storeCar.carName',
            'storeCarComponent.storeCar.store', 'acceptedOffer.store', 'payment'];
    }

    public function acceptOffer(AcceptOfferRequest $request, Order $order)
    {
        $this->authorize('update', $order);

        $offer = $order->offers()->findOrFail($request->offer_id);

        $order = $this->orders->acceptOffer($order, $offer);

        $order->load(['vehicleDetails', 'images', 'component', 'storeCarComponent.storeCar.store', 'offers.store', 'offers.images', 'acceptedOffer.store']);

        return $this->success(new OrderResource($order));
    }

    public function cancel(Order $order)
    {
        $this->authorize('update', $order);

        $order = $this->orders->cancel($order);

        $order->load(['vehicleDetails', 'images', 'component', 'storeCarComponent.storeCar.store', 'offers.store', 'offers.images', 'acceptedOffer.store']);

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

        $order->refresh()->load(['vehicleDetails', 'images', 'component', 'storeCarComponent.storeCar.store', 'acceptedOffer.store']);

        return $this->success([
            'payment' => new PaymentResource($payment),
            'order' => new OrderResource($order),
        ]);
    }

    public function received(Order $order)
    {
        $this->authorize('update', $order);

        $order = $this->orders->complete($order);

        $order->load(['vehicleDetails', 'images', 'component', 'storeCarComponent.storeCar.store', 'offers.store', 'offers.images', 'acceptedOffer.store']);

        return $this->success(new OrderResource($order));
    }
}
