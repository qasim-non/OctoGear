<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\UpdateProviderStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;

class ProviderStoreController extends Controller
{
    public function show(Store $store)
    {
        $this->authorize('manage', $store);

        $store->load(['city', 'pictures']);

        return $this->success(new StoreResource($store));
    }

    public function update(UpdateProviderStoreRequest $request, Store $store)
    {
        $this->authorize('manage', $store);

        $store->update($request->validated());

        $store->load(['city', 'pictures']);

        return $this->success(new StoreResource($store), __('auth.store.updated'));
    }
}
