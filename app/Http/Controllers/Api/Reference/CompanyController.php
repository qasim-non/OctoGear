<?php

namespace App\Http\Controllers\Api\Reference;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reference\ReferenceCatalogRequest;
use App\Http\Resources\ReferenceResource;
use App\Models\CarCompany;

class CompanyController extends Controller
{
    public function index(ReferenceCatalogRequest $request)
    {
        $filters = $request->validated();
        $companies = CarCompany::query()
            ->select('id', 'name_en', 'name_ar')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($names) => $names->where('name_en', 'like', "%{$search}%")
                ->orWhere('name_ar', 'like', "%{$search}%")
            ))
            ->orderBy('name_en')->orderBy('id')
            ->paginate($filters['per_page'] ?? 20);

        return $this->paginated($companies->through(fn ($company) => (new ReferenceResource($company))->resolve($request)));
    }

    public function names(ReferenceCatalogRequest $request, CarCompany $company)
    {
        $filters = $request->validated();
        $names = $company->carNames()
            ->select('id', 'name_en', 'name_ar')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($names) => $names->where('name_en', 'like', "%{$search}%")
                ->orWhere('name_ar', 'like', "%{$search}%")
            ))
            ->orderBy('name_en')->orderBy('id')
            ->paginate($filters['per_page'] ?? 20);

        return $this->paginated($names->through(fn ($name) => (new ReferenceResource($name))->resolve($request)));
    }
}
