<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIndexRequest;
use App\Http\Requests\Admin\StoreStatusRequest;
use App\Http\Resources\AdminStoreResource;
use App\Models\Store;
use App\Services\AdminStoreService;

class AdminStoreController extends Controller
{
    public function __construct(
        protected AdminStoreService $service,
    ) {}

    public function index(StoreIndexRequest $request)
    {
        $paginator = $this->service->index($request->validated());

        return $this->paginated(
            $paginator->through(fn (Store $store) => new AdminStoreResource($store)),
        );
    }

    public function show(Store $store)
    {
        return $this->success(new AdminStoreResource($this->service->show($store)));
    }

    public function status(StoreStatusRequest $request, Store $store)
    {
        $status = StoreStatus::from($request->validated('status'));
        $store = $this->service->setStatus($store, $status);
        $store = $this->service->show($store);

        $message = $status === StoreStatus::Inactive
            ? __('auth.admin.stores.suspended')
            : __('auth.admin.stores.activated');

        return $this->success(new AdminStoreResource($store), $message);
    }
}
