<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\UpdateProviderStoreRequest;
use App\Http\Resources\StoreRequestResource;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Services\StoreChangeRequestService;

class ProviderStoreController extends Controller
{
    public function __construct(private StoreChangeRequestService $changes) {}

    public function index()
    {
        $stores = auth()->user()
            ->stores()
            ->with(['city', 'pictures', 'companies', 'pendingChangeRequest'])
            ->get();

        return $this->success(StoreResource::collection($stores));
    }

    public function show(Store $store)
    {
        $this->authorize('manage', $store);

        return $this->success(new StoreResource($store->load(['city', 'pictures', 'companies', 'pendingChangeRequest'])))
            ->header('Cache-Control', 'no-store, private');
    }

    public function update(UpdateProviderStoreRequest $request, Store $store)
    {
        $this->authorize('manage', $store);

        $proposal = $this->changes->submit($request->user(), $store, $request->validated());

        return $this->created(new StoreRequestResource($proposal), __('store_changes.submitted'))
            ->header('Cache-Control', 'no-store, private');
    }
}
