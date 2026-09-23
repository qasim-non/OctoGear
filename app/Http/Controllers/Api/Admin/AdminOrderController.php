<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderIndexRequest;
use App\Http\Resources\AdminOrderResource;
use App\Models\Order;
use App\Services\AdminOrderService;

class AdminOrderController extends Controller
{
    public function __construct(
        protected AdminOrderService $service,
    ) {}

    public function index(OrderIndexRequest $request)
    {
        $paginator = $this->service->index($request->validated());

        return $this->paginated(
            $paginator->through(fn (Order $order) => new AdminOrderResource($order)),
        );
    }

    public function show(Order $order)
    {
        return $this->success(new AdminOrderResource($this->service->show($order)));
    }

    public function cancel(Order $order)
    {
        $this->service->cancel($order);

        return $this->success(
            new AdminOrderResource($this->service->show($order)),
            __('auth.admin.orders.cancelled'),
        );
    }

    public function refund(Order $order)
    {
        $order = $this->service->refund($order);

        return $this->success(
            new AdminOrderResource($order),
            __('auth.admin.orders.refunded'),
        );
    }
}
