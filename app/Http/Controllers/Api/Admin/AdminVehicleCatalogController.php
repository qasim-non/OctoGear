<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CarCompanyIndexRequest;
use App\Http\Requests\Admin\CarNameIndexRequest;
use App\Http\Requests\Admin\VehicleCatalogDeleteRequest;
use App\Http\Requests\Admin\VehicleCatalogSaveRequest;
use App\Http\Resources\AdminCarCompanyResource;
use App\Http\Resources\AdminCarNameResource;
use App\Models\CarCompany;
use App\Models\CarName;
use App\Services\AdminVehicleCatalogQueryService;
use App\Services\AdminVehicleCatalogService;
use Illuminate\Http\JsonResponse;

class AdminVehicleCatalogController extends Controller
{
    public function __construct(private AdminVehicleCatalogQueryService $catalog, private AdminVehicleCatalogService $writer) {}

    public function countryOptions(): JsonResponse
    {
        $this->authorize('viewAny', CarCompany::class);

        return $this->success($this->catalog->countryOptions())
            ->header('Cache-Control', 'no-store, private');
    }

    public function createCompany(VehicleCatalogSaveRequest $request): JsonResponse
    {
        $this->authorize('create', CarCompany::class);

        return $this->created(new AdminCarCompanyResource($this->writer->save(CarCompany::class, $request->validated())));
    }

    public function updateCompany(VehicleCatalogSaveRequest $request, CarCompany $company): JsonResponse
    {
        $this->authorize('update', $company);

        return $this->success(new AdminCarCompanyResource($this->writer->save(CarCompany::class, $request->validated(), $company->id)));
    }

    public function deleteCompany(VehicleCatalogDeleteRequest $request, CarCompany $company): JsonResponse
    {
        $this->authorize('delete', $company);
        $this->writer->delete(CarCompany::class, $company->id, $request->validated('version'));

        return $this->success();
    }

    public function createName(VehicleCatalogSaveRequest $request): JsonResponse
    {
        $this->authorize('create', CarName::class);

        return $this->created(new AdminCarNameResource($this->writer->save(CarName::class, $request->validated())));
    }

    public function updateName(VehicleCatalogSaveRequest $request, CarName $name): JsonResponse
    {
        $this->authorize('update', $name);

        return $this->success(new AdminCarNameResource($this->writer->save(CarName::class, $request->validated(), $name->id)));
    }

    public function deleteName(VehicleCatalogDeleteRequest $request, CarName $name): JsonResponse
    {
        $this->authorize('delete', $name);
        $this->writer->delete(CarName::class, $name->id, $request->validated('version'));

        return $this->success();
    }

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
