<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\ProviderDashboardRequest;
use App\Http\Resources\ProviderDashboardOrderResource;
use App\Http\Resources\ProviderDashboardResource;
use App\Services\ProviderDashboardService;
use Illuminate\Support\Facades\DB;

class ProviderDashboardController extends Controller
{
    public function __construct(private ProviderDashboardService $dashboard) {}

    public function show(ProviderDashboardRequest $request)
    {
        return $this->success(new ProviderDashboardResource($this->dashboard->summary(
            $request->user()->id,
            $request->filled('store_id') ? $request->integer('store_id') : null,
            $request->input('period', 'month'),
        )));
    }

    public function orders(ProviderDashboardRequest $request)
    {
        // Paginator count, rows, and eager-loaded context share one read snapshot.
        $orders = DB::transaction(fn () => $this->dashboard->orders(
            $request->user()->id,
            $request->filled('store_id') ? $request->integer('store_id') : null,
            $request->string('section')->toString(),
        )->paginate(15));

        return $this->paginated($orders->through(fn ($order) => new ProviderDashboardOrderResource($order)));
    }
}
