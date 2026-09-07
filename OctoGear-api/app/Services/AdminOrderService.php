<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

/**
 * Admin-facing read and lifecycle operations over all orders.
 *
 * Listing uses a single $filters array from OrderIndexRequest::validated(),
 * mirroring the other Admin* services. Lifecycle actions delegate to the
 * existing OrderService/PaymentService so every transition still runs through
 * the shared business rules; only the after-payment refund path is exercised
 * here (customer-facing code cannot cancel a paid order).
 */
class AdminOrderService
{
    public function __construct(
        protected OrderService $orders,
        protected PaymentService $payments,
    ) {}

    public function index(array $filters): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;
        $type = $filters['type'] ?? null;
        $customer = $filters['customer'] ?? null;
        $mobile = $filters['mobile'] ?? null;

        return Order::query()
            ->with(['customer', 'carModel', 'acceptedStore'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($type, fn ($query) => $query->where('order_type', $type))
            ->when($customer, fn ($query) => $query->whereHas(
                'customer',
                fn ($query) => $query->where('full_name', 'like', "%{$customer}%")
            ))
            ->when($mobile, fn ($query) => $query->whereHas(
                'customer',
                fn ($query) => $query->where('mobile', 'like', "%{$mobile}%")
            ))
            ->latest()
            ->paginate(15);
    }

    public function show(Order $order): Order
    {
        return $order->load([
            'customer',
            'carModel',
            'storeCarComponent.storeCar.store',
            'acceptedStore',
            'payment',
            'offers.store',
        ]);
    }

    public function cancel(Order $order): Order
    {
        return $this->orders->cancel($order);
    }

    public function refund(Order $order): Order
    {
        try {
            $this->payments->refund($order);
        } catch (RuntimeException $e) {
            throw new BusinessRuleException(
                'Payment could not be refunded.',
                'auth.admin.orders.refund_failed',
                [],
                $e->getCode() ?: 400,
            );
        }

        return $this->show($order);
    }
}
