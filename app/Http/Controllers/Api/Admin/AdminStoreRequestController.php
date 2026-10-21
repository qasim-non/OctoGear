<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectStoreRequestRequest;
use App\Http\Requests\Admin\StoreRequestIndexRequest;
use App\Http\Resources\AdminStoreRequestResource;
use App\Models\StoreRequest;
use App\Services\AdminStoreRequestService;

class AdminStoreRequestController extends Controller
{
    public function __construct(
        protected AdminStoreRequestService $service,
    ) {}

    public function index(StoreRequestIndexRequest $request)
    {
        $paginator = $this->service->index($request->validated('status'), $request->validated());

        return $this->paginated(
            $paginator->through(fn (StoreRequest $storeRequest) => new AdminStoreRequestResource($storeRequest)),
        );
    }

    public function show(StoreRequest $storeRequest)
    {
        $storeRequest = $this->service->show($storeRequest);

        return $this->success(new AdminStoreRequestResource($storeRequest));
    }

    public function accept(StoreRequest $storeRequest)
    {
        $this->service->accept($storeRequest, auth()->user());

        return $this->success(
            new AdminStoreRequestResource($this->service->show($storeRequest->fresh())),
            __('auth.admin.store_requests.accepted'),
        );
    }

    public function reject(RejectStoreRequestRequest $request, StoreRequest $storeRequest)
    {
        $this->service->reject($storeRequest, auth()->user(), $request->validated('reason'));

        return $this->success(
            new AdminStoreRequestResource($this->service->show($storeRequest->fresh())),
            __('auth.admin.store_requests.rejected'),
        );
    }
}
