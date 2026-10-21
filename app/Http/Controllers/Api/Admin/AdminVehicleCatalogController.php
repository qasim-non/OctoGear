<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CarCompanyIndexRequest;
use App\Http\Requests\Admin\CarNameIndexRequest;
use App\Http\Resources\AdminCarCompanyResource;
use App\Http\Resources\AdminCarNameResource;
use App\Models\CarCompany;
use App\Models\CarName;
use App\Services\AdminVehicleCatalogQueryService;
use Illuminate\Http\JsonResponse;

class AdminVehicleCatalogController extends Controller
{
    public function __construct(private AdminVehicleCatalogQueryService $catalog) {}

    public function companies(CarCompanyIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', CarCompany::class);

        return $this->paginated($this->catalog->companies($request->validated())
            ->through(fn (CarCompany $company) => new AdminCarCompanyResource($company)))
            ->header('Cache-Control', 'no-store, private');
    }

    public function companyOptions(): JsonResponse
    {
        $this->authorize('viewAny', CarCompany::class);

        return $this->success($this->catalog->companyOptions())->header('Cache-Control', 'no-store, private');
    }

    public function names(CarNameIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', CarName::class);

        return $this->paginated($this->catalog->names($request->validated())
            ->through(fn (CarName $name) => new AdminCarNameResource($name)))
            ->header('Cache-Control', 'no-store, private');
    }
}
