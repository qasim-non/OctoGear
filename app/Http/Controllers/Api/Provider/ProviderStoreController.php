<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\UpdateProviderStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Services\StoreMediaService;

class ProviderStoreController extends Controller
{
    public function __construct(private StoreMediaService $media) {}

    public function index()
    {
        $stores = auth()->user()
            ->stores()
            ->with(['city', 'pictures'])
            ->get();

        return $this->success(StoreResource::collection($stores));
    }

    public function update(UpdateProviderStoreRequest $request, Store $store)
    {
        $this->authorize('manage', $store);

        $store = $this->media->update($store, $request->validated());

        $store->load(['city', 'pictures']);

        return $this->success(new StoreResource($store), __('auth.store.updated'));
    }
}
