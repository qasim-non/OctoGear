<?php

namespace App\Http\Controllers\Api\Reference;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reference\ReferenceCatalogRequest;
use App\Http\Resources\ReferenceResource;
use App\Models\CarCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    public function index(ReferenceCatalogRequest $request)
    {
        $filters = $request->validated();

        return $this->catalogResponse(CarCompany::query(), $filters, $request);
    }

    public function names(ReferenceCatalogRequest $request, CarCompany $company)
    {
        $filters = $request->validated();

        return $this->catalogResponse($company->carNames(), $filters, $request);
    }

    private function catalogResponse(Builder|Relation $query, array $filters, ReferenceCatalogRequest $request): JsonResponse
    {
        $items = $query
            ->select('id', 'name_en', 'name_ar')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($names) => $names->where('name_en', 'like', "%{$search}%")
                ->orWhere('name_ar', 'like', "%{$search}%")
            ))
            ->orderBy('name_en')->orderBy('id')
            ->paginate($filters['per_page'] ?? 20);

        return $this->paginated($items->through(fn ($item) => (new ReferenceResource($item))->resolve($request)));
    }
}
